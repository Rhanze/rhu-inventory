<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\HealthUnit;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ImportedActivityLogController extends Controller
{
    public function store(Request $request)
    {
        abort_unless(session('user_id') && in_array(session('role'), ['admin', 'staff'], true), 403);
        $validated = $request->validate([
            'source_user' => ['nullable', 'string', 'max:150'],
            'source_module' => ['required', 'string', 'max:100'],
            'source_action' => ['required', 'string', 'max:100'],
            'source_description' => ['required', 'string', 'max:10000'],
            'source_date' => ['nullable', 'string', 'max:100'],
            'source_location' => ['nullable', 'string', 'max:255'],
        ]);
        $location = session('health_unit_id')
            ? HealthUnit::findOrFail(session('health_unit_id'))->name
            : 'Provincial Health Office';
        if (! empty($validated['source_location'])
            && mb_strtolower($validated['source_location']) !== mb_strtolower($location)) {
            throw ValidationException::withMessages([
                'source_location' => 'The source location must match your current RHU or PHO scope.',
            ]);
        }

        ActivityLog::create([
            'health_unit_id' => session('health_unit_id'),
            'user_id' => session('user_id'),
            'module' => 'Imported History',
            'action' => 'Import',
            'description' => implode("\n", [
                'Historical CSV record with file-provided source metadata',
                'Source actor — '.($validated['source_user'] ?? 'Unrecorded'),
                'Source module — '.$validated['source_module'],
                'Source action — '.$validated['source_action'],
                'Source date and time — '.($validated['source_date'] ?? 'Unrecorded'),
                'Source location — '.($validated['source_location'] ?? $location),
                'Source description — '.$validated['source_description'],
            ]),
            'created_at' => now(),
        ]);

        return back()->with('success', 'Historical activity source record imported.');
    }
}
