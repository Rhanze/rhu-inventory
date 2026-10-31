<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class SchedulerController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(config('portals.surface') === 'management', 404);
        $secret = config('serverless.cron_secret');
        abort_unless(is_string($secret) && strlen($secret) >= 32 && hash_equals($secret, $request->bearerToken() ?? ''), 403);

        $lock = Cache::lock('scheduled-http-invocation', 360);
        if (! $lock->get()) {
            return response()->json(['status' => 'already_running'], 202);
        }
        try {
            $minute = 'scheduled-http-minute:'.now()->format('YmdHi');
            if (Cache::has($minute)) {
                return response()->json(['status' => 'already_processed']);
            }
            $exitCode = Artisan::call('schedule:run', ['--no-interaction' => true]);
            abort_if($exitCode !== 0, 500, 'Scheduler failed; inspect management logs.');
            Cache::put($minute, true, 120);

            return response()->json(['status' => 'processed']);
        } finally {
            $lock->release();
        }
    }
}
