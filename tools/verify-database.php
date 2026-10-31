<?php

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
$admin = DB::connection('mysql')->getPdo();
$database = 'rhu_repair_test_'.bin2hex(random_bytes(6));
$created = false;
$workers = [];
$readyFiles = [];
$exitCode = 1;
try {
    if (! preg_match('/^rhu_repair_test_[a-f0-9]{12}$/', $database)) {
        throw new RuntimeException('Invalid disposable database name.');
    }
    $admin->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database, 'DB_URL' => '',
        'DB_HOST' => $settings['host'], 'DB_PORT' => (string) $settings['port'], 'DB_USERNAME' => $settings['username'],
        'DB_PASSWORD' => $settings['password'], 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'BCRYPT_ROUNDS' => '4'];
    $suite = new Process([PHP_BINARY, 'vendor/phpunit/phpunit/phpunit', '-c', 'phpunit.xml.dist', '--stop-on-error'], base_path(), $environment);
    $suite->setTimeout(180);
    $suite->run(fn ($type, $output) => print ($output));
    if (! $suite->isSuccessful()) {
        throw new RuntimeException('MariaDB/MySQL regression suite failed.');
    }
    config(['database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null,
        'database.default' => 'mysql', 'session.driver' => 'array', 'cache.default' => 'array']);
    DB::purge('mysql');
    $source = HealthUnit::create(['name' => 'Concurrency source', 'facility_type' => 'Rural Health Unit']);
    $destination = HealthUnit::create(['name' => 'Concurrency destination', 'facility_type' => 'Rural Health Unit']);
    $user = User::factory()->create(['health_unit_id' => $source->id, 'role' => 'admin']);
    session(['user_id' => $user->id, 'role' => 'admin', 'health_unit_id' => $source->id]);
    $medicine = Medicine::create(['medicine_name' => 'Concurrency fixture', 'category' => 'medicine']);
    $batch = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $source->id,
        'batch_no' => 'CONCURRENT', 'expiration_date' => today()->addMonth(), 'available_stock' => 10, 'allocated_stock' => 0]);
    $transfer = TransferRequest::create(['medicine_id' => $medicine->id, 'from_health_unit_id' => $source->id,
        'to_health_unit_id' => $destination->id, 'quantity' => 7, 'requested_by' => $user->id, 'status' => 'pending']);
    app(TransferService::class)->reserve($transfer->id);
    DB::beginTransaction();
    TransferRequest::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
    try {
        foreach (range(1, 2) as $number) {
            $readyFile = storage_path('framework/testing-views/receipt-ready-'.bin2hex(random_bytes(8)));
            $readyFiles[] = $readyFile;
            $worker = new Process([PHP_BINARY, 'tests/Support/complete-transfer.php', (string) $transfer->id, $readyFile], base_path(), $environment);
            $worker->setTimeout(30);
            $worker->start();
            $workers[] = $worker;
            // Windows Process output polling may consume READY before waitUntil sees it.
            $deadline = microtime(true) + 30;
            while (true) {
                clearstatcache(true, $readyFile);
                if (is_file($readyFile)) {
                    break;
                }
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Receipt worker did not become ready.');
                }
                usleep(10000);
            }
        }
        DB::commit();
    } catch (Throwable $e) {
        DB::rollBack();
        throw $e;
    }
    foreach ($workers as $worker) {
        $worker->wait();
        if (! $worker->isSuccessful()) {
            throw new RuntimeException('Receipt worker failed: '.$worker->getErrorOutput());
        }
    }
    $received = Inventory::where('health_unit_id', $destination->id)->sum('available_stock');
    if ((int) $received !== 7 || $batch->fresh()->available_stock !== 3 || $batch->fresh()->allocated_stock !== 0 || StockLog::where('transfer_id', $transfer->id)->count() !== 2) {
        throw new RuntimeException('Competing receipts changed stock more than once.');
    }
    echo "\nPASS: competing receipt processes moved stock exactly once.\n";
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
