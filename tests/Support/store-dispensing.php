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
    throw new RuntimeException('Dispensing worker requires a disposable test database.');
}

[, $medicineId, $readyFile, $actorId] = $argv;
if (realpath(dirname($readyFile)) !== realpath(storage_path('framework/testing-views'))
    || ! preg_match('/^receipt-ready-[a-f0-9]{16}$/', basename($readyFile))) {
    throw new RuntimeException('Invalid dispensing readiness file.');
}

config(['view.compiled' => dirname($readyFile)]);
session(['user_id' => (int) $actorId]);
$http = $app->make(HttpKernel::class);
$form = $http->handle(Request::create(route('dispensing.create')));
if ($form->getStatusCode() !== 200 || ! $form->getOriginalContent() instanceof View) {
    throw new RuntimeException('The dispensing form could not be opened.');
}

$payload = [
    'submission_token' => $form->getOriginalContent()->getData()['submissionToken'],
    'medicine_id' => (int) $medicineId,
    'quantity_dispensed' => 8,
    'date_dispensed' => today()->toDateString(),
];
$preview = $http->handle(Request::create(route('dispensing.preview'), 'POST', $payload, [], [], [
    'HTTP_ACCEPT' => 'application/json',
]));
$token = json_decode($preview->getContent(), true)['token'] ?? null;
if ($preview->getStatusCode() !== 200 || ! $token) {
    throw new RuntimeException('The dispensing preview could not be obtained.');
}
$payload['lot_preview'] = $token;
file_put_contents($readyFile, 'ready');

$submit = fn () => $http->handle(Request::create(route('dispensing.store'), 'POST', $payload));
$response = $submit();
if ($response->getStatusCode() === 302
    && session('error') === 'The batches have changed. Review the refreshed batch quantities before saving again.') {
    echo "stale\n";
    exit;
}
if ($response->headers->get('location') !== route('dispensing.index') || ! session('success')) {
    throw new RuntimeException('The dispensing submission failed.');
}

session()->forget('success');
$replay = $submit();
if ($replay->headers->get('location') !== route('dispensing.index') || ! session('success')) {
    throw new RuntimeException('The dispensing replay failed.');
}
echo "saved and replayed\n";
