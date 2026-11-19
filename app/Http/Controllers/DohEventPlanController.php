<?php

namespace App\Http\Controllers;

use App\Models\DoHProgram;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DohEventPlanController extends Controller
{
    public function store(Request $request, DoHProgram $program)
    {
        abort_unless(session('health_unit_id') && in_array(session('role'), ['admin', 'staff'], true), 403);
        $valid = $request->validate([
            'event_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.today()->addYear()->toDateString()],
            'items' => ['nullable', 'array', 'max:200'],
            'items.*.medicine_id' => ['required', 'integer', 'distinct', Rule::exists('medicines', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'additional_medicine_id' => ['nullable', 'integer', Rule::exists('medicines', 'id')->whereNull('deleted_at')],
            'additional_quantity' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'reset_plan' => ['nullable', 'boolean'],
        ]);
        $date = Carbon::parse($valid['event_date']);
        abort_unless($program->startsBetween($date, $date) !== [], 422, 'Choose the start date of an active program occurrence.');
        $scope = ['health_unit_id' => (int) session('health_unit_id'), 'doh_program_id' => (int) $program->id,
            'event_date' => $date->toDateString()];
        $items = collect($valid['items'] ?? [])->keyBy('medicine_id');
        if (! empty($valid['additional_medicine_id'])) {
            if ($items->has($valid['additional_medicine_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'additional_medicine_id' => 'This material is already listed. Update its program quantity instead.',
                ]);
            }
            $items->put($valid['additional_medicine_id'], ['medicine_id' => $valid['additional_medicine_id'],
                'quantity' => $valid['additional_quantity'] ?? null]);
        }

        DB::transaction(function () use ($scope, $program, $items, $valid) {
            DoHProgram::whereKey($program->id)->lockForUpdate()->firstOrFail();
            DB::table('doh_event_materials')->where($scope)->delete();
            if (! ($valid['reset_plan'] ?? false)) {
                foreach ($items as $item) {
                    DB::table('doh_event_materials')->insert($scope + ['medicine_id' => $item['medicine_id'],
                        'quantity' => $item['quantity'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });

        return redirect()->route('vaccination.calendar', ['month' => $date->format('Y-m'), 'day' => $date->toDateString()])
            ->with('success', 'Program material plan saved for your RHU.');
    }
}
