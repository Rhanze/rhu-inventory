<?php

use App\Models\DispensingRecord;
use App\Models\DohShipment;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\TransferRequest;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/** Verify migrations, regressions, and competing receipts without touching application data. */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$settings = config('database.connections.mysql');
$admin = null;
$database = 'rhu_repair_test_'.bin2hex(random_bytes(6));
$created = false;
$workers = [];
$readyFiles = [];
$exitCode = 1;
try {
    // Keep connection failures inside the runner's error/exit handling.
    $admin = DB::connection('mysql')->getPdo();
    if (! preg_match('/^rhu_repair_test_[a-f0-9]{12}$/', $database)) {
        throw new RuntimeException('Invalid disposable database name.');
    }
    $admin->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database, 'DB_URL' => '',
        'DB_HOST' => $settings['host'], 'DB_PORT' => (string) $settings['port'], 'DB_USERNAME' => $settings['username'],
        'DB_PASSWORD' => $settings['password'], 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'BCRYPT_ROUNDS' => '4',
        'APP_URL' => 'http://localhost', 'REQUIRE_ADMIN_MFA' => 'false', 'PORTAL_DOMAIN_SPLIT' => 'false'];
    $suite = new Process([PHP_BINARY, 'vendor/phpunit/phpunit/phpunit', '-c', 'phpunit.xml.dist', '--stop-on-error'], base_path(), $environment);
    $suite->setTimeout(180);
    $suite->run(fn ($type, $output) => print ($output));
    if (! $suite->isSuccessful()) {
        throw new RuntimeException('MariaDB/MySQL regression suite failed.');
    }
    config(['database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null,
        'database.default' => 'mysql', 'session.driver' => 'array', 'cache.default' => 'array']);
    DB::purge('mysql');

    $competeForLockedRow = function (string $table, int $id, string $script, int $recordId, array $actorArgs) use ($database, $environment, &$workers, &$readyFiles): array {
        $competingWorkers = [];
        DB::beginTransaction();
        try {
            DB::table($table)->where('id', $id)->lockForUpdate()->first();
            foreach ($actorArgs as $args) {
                $readyFile = storage_path('framework/testing-views/receipt-ready-'.bin2hex(random_bytes(8)));
                $readyFiles[] = $readyFile;
                $worker = new Process([PHP_BINARY, $script, (string) $recordId, $readyFile, ...$args], base_path(), $environment);
                $worker->setTimeout(30);
                $worker->start();
                $workers[] = $competingWorkers[] = $worker;
            }

            $deadline = microtime(true) + 15;
            do {
                $waiting = collect(DB::select('SHOW FULL PROCESSLIST'))->filter(fn ($process) => $process->db === $database
                    && str_contains($process->Info ?? '', 'from `'.$table.'`')
                    && str_contains($process->Info ?? '', 'for update'))->count();
                if ($waiting === count($competingWorkers)) {
                    break;
                }
                foreach ($competingWorkers as $worker) {
                    if (! $worker->isRunning()) {
                        throw new RuntimeException('Receipt worker stopped before acquiring its lock: '.$worker->getErrorOutput().$worker->getOutput());
                    }
                }
                usleep(10000);
            } while (microtime(true) < $deadline);

            if ($waiting !== count($competingWorkers)) {
                throw new RuntimeException('Receipt workers did not both reach the database lock.');
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        foreach ($competingWorkers as $worker) {
            $worker->wait();
            if (! $worker->isSuccessful()) {
                throw new RuntimeException('Receipt worker failed: '.$worker->getErrorOutput().$worker->getOutput());
            }
        }

        return array_map(fn (Process $worker) => trim($worker->getOutput()), $competingWorkers);
    };

    $source = HealthUnit::create(['name' => 'Concurrency source', 'facility_type' => 'Rural Health Unit']);
    $destination = HealthUnit::create(['name' => 'Concurrency destination', 'facility_type' => 'Rural Health Unit']);
    $user = User::factory()->create(['health_unit_id' => $source->id, 'role' => 'admin']);
    $receiver = User::factory()->create(['health_unit_id' => $destination->id, 'role' => 'staff']);
    session(['user_id' => $user->id, 'role' => 'admin', 'health_unit_id' => $source->id]);
    $medicine = Medicine::create(['medicine_name' => 'Concurrency fixture', 'category' => 'medicine']);
    $batch = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $source->id,
        'batch_no' => 'CONCURRENT', 'expiration_date' => today()->addMonth(), 'available_stock' => 10, 'allocated_stock' => 0]);
    $transfer = TransferRequest::create(['medicine_id' => $medicine->id, 'from_health_unit_id' => $source->id,
        'to_health_unit_id' => $destination->id, 'quantity' => 7, 'requested_by' => $user->id, 'status' => 'pending']);
    app(TransferService::class)->reserve($transfer->id);
    $transferActor = [(string) $receiver->id, (string) $destination->id];
    $competeForLockedRow('transfer_requests', $transfer->id, 'tests/Support/complete-transfer.php', $transfer->id, [$transferActor, $transferActor]);
    $received = Inventory::where('health_unit_id', $destination->id)->sum('available_stock');
    if ((int) $received !== 7 || $batch->fresh()->available_stock !== 3 || $batch->fresh()->allocated_stock !== 0 || StockLog::where('transfer_id', $transfer->id)->count() !== 2) {
        throw new RuntimeException('Competing receipts changed stock more than once.');
    }
    echo "\nPASS: competing transfer confirmation processes moved stock exactly once.\n";

    $pho = HealthUnit::create(['name' => 'Concurrency PHO', 'facility_type' => 'Provincial Health Office']);
    $firstReceiver = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
    $secondReceiver = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
    $locations = $competeForLockedRow('health_units', $pho->id, 'tests/Support/store-doh-shipment.php', $medicine->id,
        [[(string) $firstReceiver->id], [(string) $secondReceiver->id]]);
    $receipt = DohShipment::where('shipment_no', 'DOH-CONCURRENT')->sole();
    $received = (int) Inventory::where('health_unit_id', $pho->id)->sum('available_stock');
    $movements = StockLog::where('health_unit_id', $pho->id)->where('movement_type', 'receipt');
    if ($locations[0] !== $locations[1] || ! str_ends_with($locations[0], '/pho-receive/'.$receipt->id)
        || $receipt->items()->count() !== 1 || $movements->count() !== 1 || (int) $movements->sum('quantity_added') !== 50 || $received !== 50) {
        throw new RuntimeException('Competing DOH submissions did not produce one 50-unit receipt.');
    }
    echo "PASS: competing DOH form submissions produced one receipt, one movement, and 50 units.\n";

    $dispensable = Medicine::create(['medicine_name' => 'Competing dispensing fixture', 'is_dispensable' => true]);
    $early = Inventory::create(['medicine_id' => $dispensable->id, 'health_unit_id' => $source->id,
        'batch_no' => 'FIRST-EXPIRY', 'expiration_date' => today()->addMonth(), 'available_stock' => 10]);
    $late = Inventory::create(['medicine_id' => $dispensable->id, 'health_unit_id' => $source->id,
        'batch_no' => 'SECOND-EXPIRY', 'expiration_date' => today()->addMonths(3), 'available_stock' => 20]);
    $dispenser = User::factory()->create(['role' => 'staff', 'health_unit_id' => $source->id]);
    $outcomes = $competeForLockedRow('inventory', $early->id, 'tests/Support/store-dispensing.php', $dispensable->id,
        [[(string) $user->id], [(string) $dispenser->id]]);
    sort($outcomes);
    $dispensing = DispensingRecord::where('medicine_id', $dispensable->id)->sole();
    $dispensingMovement = DB::table('stock_movements')->where('owner_type', 'dispensing_records')->where('owner_id', $dispensing->id)->sole();
    if ($outcomes !== ['saved and replayed', 'stale'] || $early->fresh()->available_stock !== 2 || $late->fresh()->available_stock !== 20
        || $dispensing->lotBreakdown() !== 'FIRST-EXPIRY (8)' || (int) $dispensingMovement->inventory_id !== $early->id
        || (int) $dispensingMovement->available_delta !== -8) {
        throw new RuntimeException('Competing dispensing forms did not preserve the confirmed batch quantities.');
    }
    echo "PASS: competing dispensing forms saved one exact issue, rejected the stale preview, and replayed the successful submission safely.\n";
    $exitCode = 0;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage().PHP_EOL);
} finally {
    foreach ($workers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop();
        }
    }
    foreach ($readyFiles as $readyFile) {
        if (is_file($readyFile)) {
            unlink($readyFile);
        }
    }
    if ($created) {
        $admin->exec("DROP DATABASE `$database`");
    }
}
exit($exitCode);
