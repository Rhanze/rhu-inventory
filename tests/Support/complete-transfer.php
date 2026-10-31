<?php

use App\Services\TransferService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = config('database.connections.mysql.database');
if (! app()->environment('testing') || ! preg_match('/^rhu_repair_test_[a-f0-9]{12}$/', $database)) {
    throw new RuntimeException('Receipt worker requires a disposable test database.');
}
if (isset($argv[2])) {
    $readyFile = $argv[2];
    if (realpath(dirname($readyFile)) !== realpath(storage_path('framework/testing-views')) || ! preg_match('/^receipt-ready-[a-f0-9]{16}$/', basename($readyFile))) {
        throw new RuntimeException('Invalid receipt readiness file.');
    }
    file_put_contents($readyFile, 'ready');
}
app(TransferService::class)->complete((int) $argv[1], null, null, true);
