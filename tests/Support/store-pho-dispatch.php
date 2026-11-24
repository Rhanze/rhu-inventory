<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

if (! app()->environment('testing') || config('database.default') !== 'mysql'
    || ! preg_match('/^rhu_repair_test_[a-f0-9]{12}$/', config('database.connections.mysql.database'))) {
    throw new RuntimeException('PHO dispatch worker requires a disposable test database.');
}

[, $medicineId, $readyFile, $actorId, $sourceId, $destinationId, $submissionToken] = $argv;
if (realpath(dirname($readyFile)) !== realpath(storage_path('framework/testing-views'))
    || ! preg_match('/^receipt-ready-[a-f0-9]{16}$/', basename($readyFile))) {
    throw new RuntimeException('Invalid PHO dispatch readiness file.');
}

session(['user_id' => (int) $actorId]);
$http = $app->make(HttpKernel::class);
file_put_contents($readyFile, 'ready');
$response = $http->handle(Request::create(route('transfers.distribute.store'), 'POST', [
    'submission_token' => $submissionToken,
    'from_health_unit_id' => (int) $sourceId,
    'to_health_unit_id' => (int) $destinationId,
    'medicine_id' => (int) $medicineId,
    'quantity' => 40,
]));

$success = session('success');
if ($response->getStatusCode() !== 302 || $response->headers->get('location') !== route('transfers.index') || ! is_string($success)) {
    throw new RuntimeException('The PHO dispatch request did not return a successful redirect.');
}

if ($success === 'This dispatch has already been processed.') {
    echo "replayed\n";
} elseif (str_starts_with($success, 'Directive sent to ')) {
    echo "created\n";
} else {
    throw new RuntimeException('The PHO dispatch request returned an unexpected result.');
}
