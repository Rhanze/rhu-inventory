<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\View\View;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

if (! app()->environment('testing') || config('database.default') !== 'mysql'
    || ! preg_match('/^rhu_repair_test_[a-f0-9]{12}$/', config('database.connections.mysql.database'))) {
    throw new RuntimeException('DOH receipt worker requires a disposable test database.');
}

[$script, $medicineId, $readyFile, $receiverId] = $argv;
if (realpath(dirname($readyFile)) !== realpath(storage_path('framework/testing-views'))
    || ! preg_match('/^receipt-ready-[a-f0-9]{16}$/', basename($readyFile))) {
    throw new RuntimeException('Invalid receipt readiness file.');
}

config(['view.compiled' => dirname($readyFile)]);
session(['user_id' => (int) $receiverId]);
$http = $app->make(HttpKernel::class);
$form = $http->handle(Request::create(route('inventory.pho_receive.create')));
if ($form->getStatusCode() !== 200 || ! $form->getOriginalContent() instanceof View) {
    throw new RuntimeException('The receipt form could not be opened.');
}
$submissionToken = $form->getOriginalContent()->getData()['submissionToken'];
file_put_contents($readyFile, 'ready');

$receipt = $http->handle(Request::create(route('inventory.pho_receive.store'), 'POST', [
    'submission_token' => $submissionToken,
    'shipment_no' => 'DOH-CONCURRENT',
    'reference_no' => 'DR-CONCURRENT',
    'received_date' => today()->toDateString(),
    'items' => [['medicine_id' => (int) $medicineId, 'quantity' => 50, 'batch_no' => 'DOH-CONCURRENT']],
]));
$location = $receipt->headers->get('location');
if ($receipt->getStatusCode() !== 302 || ! session('success') || ! $location) {
    throw new RuntimeException('The receipt submission failed.');
}
echo $location.PHP_EOL;
