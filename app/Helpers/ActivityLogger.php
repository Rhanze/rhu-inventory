<?php

namespace App\Helpers;

use App\Models\ActivityLog;

class ActivityLogger
{
    public static function log($module, $action, $description, $healthUnitId = null, $userId = null)
    {
        $healthUnitId = $healthUnitId ?? session('health_unit_id');
        $userId = $userId ?? session('user_id');

        ActivityLog::create([
            'health_unit_id' => $healthUnitId,
            'user_id' => $userId,
            'module' => $module,
            'action' => $action,
            'description' => $description,
            'created_at' => now(),
        ]);
    }
}
