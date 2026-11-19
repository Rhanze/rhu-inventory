<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Support\BarangayDemandScope;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarEventController extends Controller
{
    private function guard()
    {
        if (!session('user_id')) return redirect()->route('login');
        if (!session('health_unit_id')) {
            return redirect()->route('dashboard')
                ->with('error', 'Calendar events are RHU-scoped.');
        }
        return null;
    }

    private function validationRules(): array
    {
        return [
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'event_date'  => 'required|date',
            'start_time'  => 'nullable|date_format:H:i',
            'end_time'    => 'nullable|date_format:H:i|after:start_time',
            'type'        => 'required|in:' . implode(',', array_keys(CalendarEvent::TYPES)),
            'priority'    => 'required|in:' . implode(',', array_keys(CalendarEvent::PRIORITIES)),
            'area_label'  => 'nullable|string|max:120',   // ← added
        ];
    }

    public function store(Request $request)
    {
        if ($redirect = $this->guard()) return $redirect;

        $validated = $request->validate($this->validationRules());
        abort_if($validated['type'] === CalendarEvent::TYPE_EPI_DAY && session('role') !== 'midwife', 403);
        $validated['area_label'] = BarangayDemandScope::validateForViewer(
            (int) session('health_unit_id'), $validated['area_label'] ?? null, 'area_label', session('role') === 'midwife'
        );

        $validated['health_unit_id'] = session('health_unit_id');
        $validated['created_by']     = session('user_id');

        // Normalise empty-string area_label to null so the DB stores NULL,
        // not "" — keeps the dedup logic in generateEpiDays() consistent.
        if (array_key_exists('area_label', $validated) && trim((string) $validated['area_label']) === '') {
            $validated['area_label'] = null;
        }

        CalendarEvent::create($validated);

        return redirect()
            ->route('vaccination.calendar', [
                'month' => Carbon::parse($validated['event_date'])->format('Y-m'),
                'day'   => $validated['event_date'],
            ])
            ->with('success', 'Event added: ' . $validated['title']);
    }

    public function update(Request $request, $id)
    {
        if ($redirect = $this->guard()) return $redirect;

        $event = CalendarEvent::forHealthUnit(session('health_unit_id'))->findOrFail($id);
        abort_if($event->isEpiDay() && session('role') !== 'midwife', 403);

        if (session('role') === 'midwife') {
            $originalArea = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $event->area_label)));
            $originalAreaIsAssigned = BarangayDemandScope::options((int) session('health_unit_id'), (int) session('user_id'))
                ->contains(fn ($area) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($area))) === $originalArea);
            $ownsEvent = (int) $event->created_by === (int) session('user_id');
            abort_unless($originalAreaIsAssigned || $ownsEvent, 403);
        }

        $validated = $request->validate($this->validationRules());
        abort_if($validated['type'] === CalendarEvent::TYPE_EPI_DAY && session('role') !== 'midwife', 403);

        if (session('role') === 'midwife' && ! $request->filled('area_label')) {
            // Retain the actual historical area when only other event details change.
            unset($validated['area_label']);
        } else {
            $validated['area_label'] = BarangayDemandScope::validateForViewer(
                (int) session('health_unit_id'), $validated['area_label'] ?? null, 'area_label', session('role') === 'midwife'
            );
        }

        if (array_key_exists('area_label', $validated) && trim((string) $validated['area_label']) === '') {
            $validated['area_label'] = null;
        }

        $event->update($validated);

        return redirect()
            ->route('vaccination.calendar', [
                'month' => Carbon::parse($validated['event_date'])->format('Y-m'),
                'day'   => $validated['event_date'],
            ])
            ->with('success', 'Event updated.');
    }

    public function destroy($id)
    {
        if ($redirect = $this->guard()) return $redirect;

        $event = CalendarEvent::forHealthUnit(session('health_unit_id'))->findOrFail($id);
        abort_if($event->isEpiDay() && session('role') !== 'midwife', 403);

        if (session('role') === 'midwife') {
            BarangayDemandScope::validateForViewer((int) session('health_unit_id'), $event->area_label, 'area_label', true);
        }

        $title = $event->title;
        $date  = $event->event_date->format('Y-m-d');

        $event->delete();

        return redirect()
            ->route('vaccination.calendar', ['month' => substr($date, 0, 7), 'day' => $date])
            ->with('success', 'Event deleted: ' . $title);
    }
}
