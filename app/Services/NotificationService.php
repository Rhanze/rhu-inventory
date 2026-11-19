<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\Inventory;
use App\Models\HealthUnit;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationService
{
    /** The unique ledger survives reading, archiving and deleting a notification. */
    public function scanDohPreparationReminders(): int
    {
        $count = 0;
        $stages = collect(config('doh_planning.reminder_days', [14, 7]))->map(fn ($day) => (int) $day)
            ->filter(fn ($day) => $day > 0 && $day <= 365)->unique()->sort()->values();
        if ($stages->isEmpty()) {
            return 0;
        }
        $users = User::where('is_active', true)->whereNotNull('health_unit_id')
            ->whereIn('role', ['admin', 'staff', 'midwife'])->get()->groupBy('health_unit_id');
        foreach (HealthUnit::whereIn('facility_type', ['Rural Health Unit', 'RHU'])->whereIn('id', $users->keys())->get() as $unit) {
            $recipients = [];
            if ($users[$unit->id]->contains(fn ($user) => in_array($user->role, ['admin', 'staff'], true))) {
                $recipients[] = null;
            }
            foreach ($users[$unit->id]->where('role', 'midwife') as $midwife) {
                $recipients[] = (int) $midwife->id;
            }
            foreach (app(DohEventPlanningService::class)->upcoming((int) $unit->id, null, $stages->max()) as $event) {
                $stage = $stages->first(fn ($day) => $event['days_away'] <= $day);
                $message = $this->dohPreparationMessage($event);
                foreach ($recipients as $recipient) {
                    $key = 'doh-prep:'.$unit->id.':'.$event['program_id'].':'.$event['date'].':'.$stage.':'.($recipient ?? 'shared');
                    $count += DB::transaction(function () use ($key, $unit, $event, $stage, $recipient, $message) {
                        if (! DB::table('doh_event_reminders')->insertOrIgnore(['dedup_key' => $key, 'created_at' => now()])) {
                            return 0;
                        }
                        $notice = Notification::create(['health_unit_id' => $unit->id, 'user_id' => $recipient,
                            'type' => 'doh_preparation', 'dedup_key' => $key,
                            'title' => $stage.'-day preparation · '.$event['name'], 'message' => $message,
                            'severity' => $event['shortage_count'] > 0 ? 'warning' : 'info',
                            'link' => route('vaccination.calendar', ['month' => substr($event['date'], 0, 7), 'day' => $event['date']]).'#selected-day',
                            'is_read' => false, 'created_at' => now()]);
                        DB::table('doh_event_reminders')->where('dedup_key', $key)->update(['notification_id' => $notice->id]);

                        return 1;
                    });
                }
            }
        }

        return $count;
    }

    private function dohPreparationMessage(array $event): string
    {
        $message = $event['name'].' starts '.Carbon::parse($event['date'])->format('M j, Y').' at '.$event['where'].'. ';
        $shortages = array_filter($event['items'], fn ($item) => ($item['stock_up'] ?? 0) > 0);
        foreach (array_slice($shortages, 0, 4) as $item) {
            $message .= 'Stock up '.number_format($item['stock_up']).' '.$item['unit'].' of '.$item['name']
                .($item['dosage'] ? ' '.$item['dosage'] : '').($item['history_ready'] ? '. ' : ' at minimum; routine-demand history is insufficient. ');
        }
        if (count($shortages) > 4) {
            $message .= (count($shortages) - 4).' more material shortages are listed in Calendar. ';
        }
        if ($event['items'] === [] || $event['unquantified_count'] > 0) {
            $message .= 'Set local quantities for materials without enough release history. ';
        } elseif ($shortages === []) {
            $message .= 'Review projected stock, expiry dates and local turnout before preparing supplies. ';
        }

        return $message.'Open Calendar to review the event plan and request stock.';
    }

    // ═══════════════════════════════════════════════════════════
    // VIEWER CONTEXT — routes every read query by role
    //   midwife       → only rows where user_id = her id
    //   admin / staff → only rows where user_id IS NULL
    // ═══════════════════════════════════════════════════════════

    private function scopeForViewer($query, ?int $healthUnitId, ?int $userId, ?string $role)
    {
        $query->where('type', '!=', 'disease_outbreak');
        if ($healthUnitId !== null || $role !== 'admin') {
            $query->where('type', '!=', Notification::TYPE_TRANSFER_PHO_REVIEW_REQUESTED);
        }

        if ($role === 'midwife') {
            $query->where('user_id', $userId);
        } else {
            $query->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId));
            if ($healthUnitId === null) {
                $query->where('type', 'not like', 'supply_request%');
            }
            if ($healthUnitId) {
                $query->where(function ($q) use ($healthUnitId) {
                    $q->where('health_unit_id', $healthUnitId)
                        ->orWhereNull('health_unit_id');
                });
            }
        }

        return $query;
    }

    // ═══════════════════════════════════════════════════════════
    // INVENTORY SCAN — RHU-shared only (admin/staff)
    // ═══════════════════════════════════════════════════════════

    public function scanAndGenerate(): int
    {
        $count = 0;

        $inventories = Inventory::with(['medicine', 'healthUnit'])
            ->where('available_stock', '>', 0)
            ->get();

        foreach ($inventories as $inventory) {
            if (! $inventory->medicine || ! $inventory->healthUnit) {
                continue;
            }

            if ($inventory->expiration_date && $inventory->expiration_date < now()->startOfDay()) {
                $count += $this->createInventoryNotification(
                    $inventory,
                    'expired',
                    'Expired Stock Alert',
                    $inventory->medicine->medicine_name.' (Batch: '.($inventory->batch_no ?? 'N/A').') expired on '.$inventory->expiration_date->format('Y-m-d').'. Please dispose immediately.',
                    'danger'
                );
            }

            if ($inventory->expiration_date &&
                $inventory->expiration_date >= now()->startOfDay() &&
                $inventory->expiration_date <= now()->addDays(30)) {
                $daysLeft = (int) today()->diffInDays($inventory->expiration_date, false);
                $count += $this->createInventoryNotification(
                    $inventory,
                    'near_expiry',
                    'Near Expiry Alert',
                    $inventory->medicine->medicine_name.' (Batch: '.($inventory->batch_no ?? 'N/A').') expires in '.$daysLeft.' days. ('.$inventory->expiration_date->format('Y-m-d').')',
                    'warning'
                );
            }
        }

        // A small batch is not a stock shortage when other usable batches remain.
        foreach (app(StockSummaryService::class)->inventories() as $summary) {
            if ($summary->available_stock <= 10) {
                $critical = $summary->available_stock <= 3;
                $severity = $critical ? 'danger' : 'warning';
                if ($this->notifyRhu($summary->health_unit_id, 'low_stock',
                    $critical ? 'Critical Low Stock Alert' : 'Low Stock Alert',
                    $summary->medicine->medicine_name.' has '.$summary->available_stock.' usable units remaining.',
                    $severity, route('inventory.index'), ['inventory_id' => $summary->id],
                    'low_stock_'.$summary->medicine_id.'_'.$severity, 24)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════
    // PUBLIC CREATORS
    // ═══════════════════════════════════════════════════════════

    public function notifyRhu(
        ?int $healthUnitId,
        string $type,
        string $title,
        string $message,
        string $severity = 'info',
        ?string $link = null,
        array $extra = [],
        ?string $dedupKey = null,
        int $dedupHours = 24
    ): ?Notification {
        if ($dedupKey) {
            $exists = Notification::where('type', $type)
                ->where('dedup_key', $dedupKey)
                ->where('health_unit_id', $healthUnitId)
                ->whereNull('user_id')
                ->where('created_at', '>=', now()->subHours($dedupHours))
                ->exists();
            if ($exists) {
                return null;
            }
        }

        return Notification::create(array_merge([
            'health_unit_id' => $healthUnitId,
            'user_id' => null,
            'type' => $type,
            'dedup_key' => $dedupKey,
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'link' => $link,
            'is_read' => false,
            'created_at' => now(),
        ], $extra));
    }

    public function notifyUser(
        int $userId,
        ?int $healthUnitId,
        string $type,
        string $title,
        string $message,
        string $severity = 'info',
        ?string $link = null,
        array $extra = [],
        ?string $dedupKey = null,
        int $dedupHours = 24
    ): ?Notification {
        if ($dedupKey) {
            $exists = Notification::where('type', $type)
                ->where('dedup_key', $dedupKey)
                ->where('health_unit_id', $healthUnitId)
                ->where('user_id', $userId)
                ->where('created_at', '>=', now()->subHours($dedupHours))
                ->exists();
            if ($exists) {
                return null;
            }
        }

        return Notification::create(array_merge([
            'health_unit_id' => $healthUnitId,
            'user_id' => $userId,
            'type' => $type,
            'dedup_key' => $dedupKey,
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'link' => $link,
            'is_read' => false,
            'created_at' => now(),
        ], $extra));
    }

    // ═══════════════════════════════════════════════════════════
    // TRIGGER #1 — Overdue vaccine doses (per-midwife digest)
    // ═══════════════════════════════════════════════════════════

    public function scanOverdueDoses(int $lookbackDays = 14): int
    {
        // immunization_schedule contains dose definitions; patient appointments
        // live in vaccination_schedules. Each recipient sees only assigned children.
        $rows = DB::table('vaccination_schedules as s')
            ->join('public_users as p', function ($join) {
                $join->on('p.id', '=', 's.public_user_id')
                    ->on('p.health_unit_id', '=', 's.health_unit_id');
            })
            ->join('users as u', function ($join) {
                $join->on('u.id', '=', 'p.assigned_midwife_id')
                    ->on('u.health_unit_id', '=', 'p.health_unit_id');
            })
            ->where('u.role', 'midwife')->where('u.is_active', true)
            ->whereIn('s.status', ['pending', 'missed'])
            ->whereNull('s.immunization_record_id')
            ->whereDate('s.due_date', '<', today())
            ->whereDate('s.due_date', '>=', today()->subDays(max(0, $lookbackDays)))
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('immunization_records as r')
                    ->join('immunization_schedule as d', function ($join) {
                        $join->on('d.antigen', '=', 'r.antigen')
                            ->on('d.dose_number', '=', 'r.dose_number');
                    })
                    ->whereColumn('r.public_user_id', 's.public_user_id')
                    ->whereColumn('r.dose_number', 's.dose_number')
                    ->where(fn ($q) => $q->whereColumn('d.antigen_label', 's.vaccine_name')
                        ->orWhereColumn('d.antigen', 's.vaccine_name'));
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('vaccination_schedules as completed')
                    ->whereColumn('completed.public_user_id', 's.public_user_id')
                    ->whereColumn('completed.vaccine_name', 's.vaccine_name')
                    ->whereColumn('completed.dose_number', 's.dose_number')
                    ->where('completed.status', 'given');
            })
            ->groupBy('s.health_unit_id', 'u.id')
            ->select('s.health_unit_id', 'u.id as user_id', DB::raw('COUNT(DISTINCT s.public_user_id) as overdue_count'))
            ->get();

        $created = 0;

        foreach ($rows as $row) {
            $n = $this->notifyUser(
                (int) $row->user_id,
                $row->health_unit_id,
                'overdue_doses',
                'Overdue Vaccine Doses',
                $row->overdue_count.' assigned '.($row->overdue_count == 1 ? 'child has' : 'children have').' overdue vaccine doses. Open the vaccination calendar to follow up.',
                'warning',
                route('vaccination.calendar'),
                [],
                'overdue_digest',
                20
            );
            if ($n) {
                $created++;
            }
        }

        return $created;
    }

    // ═══════════════════════════════════════════════════════════
    // TRIGGER #3 — Low stock digest (nightly, per-RHU)
    // ═══════════════════════════════════════════════════════════

    public function scanLowStockDigest(int $threshold = 10): int
    {
        $rows = app(StockSummaryService::class)->inventories()
            ->filter(fn ($item) => $item->available_stock <= $threshold)
            ->groupBy('health_unit_id')
            ->map(fn ($items, $unitId) => (object) ['health_unit_id' => (int) $unitId, 'low_count' => $items->count()]);

        $created = 0;

        foreach ($rows as $row) {
            $count = $row->low_count;
            $title = 'Low Stock Alert';
            $message = $count.' medicine'.($count == 1 ? '' : 's').
                       ' at your RHU '.($count == 1 ? 'is' : 'are').
                       ' below reorder level. Open Supply Requests to restock.';

            $dedup = 'low_stock_digest';

            if ($this->notifyRhu(
                $row->health_unit_id,
                'low_stock_digest',
                $title,
                $message,
                'warning',
                route('supply_requests.create'),
                [],
                $dedup,
                20
            )) {
                $created++;
            }

            $midwives = DB::table('users')
                ->where('health_unit_id', $row->health_unit_id)
                ->where('role', 'midwife')
                ->pluck('id');

            foreach ($midwives as $midwifeUserId) {
                if ($this->notifyUser(
                    (int) $midwifeUserId,
                    $row->health_unit_id,
                    'low_stock_digest',
                    $title,
                    $message,
                    'warning',
                    route('supply_requests.create'),
                    [],
                    $dedup,
                    20
                )) {
                    $created++;
                }
            }
        }

        return $created;
    }

    // ═══════════════════════════════════════════════════════════
    // TRIGGER #6 — EPI schedule due tomorrow (per-midwife)
    // ═══════════════════════════════════════════════════════════

    public function scanEpiReminders(): int
    {
        $tomorrow = now()->addDay()->toDateString();

        $dateCol = null;
        foreach (['event_date', 'start_date', 'date'] as $candidate) {
            if (Schema::hasColumn('calendar_events', $candidate)) {
                $dateCol = $candidate;
                break;
            }
        }
        if (! $dateCol) {
            return 0;
        }

        $hasType = Schema::hasColumn('calendar_events', 'type');
        $hasEventType = Schema::hasColumn('calendar_events', 'event_type');

        $events = DB::table('calendar_events')
            ->whereDate($dateCol, $tomorrow)
            ->whereNotNull('health_unit_id')
            ->when(Schema::hasColumn('calendar_events', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->where(function ($q) use ($hasType, $hasEventType) {
                $q->where('title', 'like', '%EPI%');
                if ($hasType) {
                    $q->orWhereIn('type', ['epi', 'immunization_day', 'immunization_drive', 'catch_up_campaign']);
                }
                if ($hasEventType) {
                    $q->orWhere('event_type', 'epi');
                }
            })
            ->get();

        $created = 0;

        foreach ($events as $event) {
            $title = 'EPI Schedule Tomorrow';
            $message = 'You have an EPI session scheduled tomorrow at your RHU'.
                       (! empty($event->title) ? ': '.$event->title : '').
                       '. Review the calendar and prepare vaccines.';

            $dedup = 'epi_reminder_'.$tomorrow;

            if ($this->notifyRhu(
                $event->health_unit_id,
                'epi_reminder',
                $title,
                $message,
                'info',
                route('vaccination.calendar'),
                [],
                $dedup,
                20
            )) {
                $created++;
            }

            $midwives = DB::table('users')
                ->where('health_unit_id', $event->health_unit_id)
                ->where('role', 'midwife')
                ->where('is_active', true)
                ->pluck('id');

            foreach ($midwives as $midwifeUserId) {
                if ($this->notifyUser(
                    (int) $midwifeUserId,
                    $event->health_unit_id,
                    'epi_reminder',
                    $title,
                    $message,
                    'info',
                    route('vaccination.calendar'),
                    [],
                    $dedup,
                    20
                )) {
                    $created++;
                }
            }
        }

        return $created;
    }

    // ═══════════════════════════════════════════════════════════
    // TRIGGER #4 — Supply request decision (Allocation-based)
    //
    // Your supply requests are Allocation rows.
    // Personal copy goes ONLY to the midwife who submitted it.
    // ═══════════════════════════════════════════════════════════

    /**
     * @param  Allocation  $allocation
     */
    public function notifySupplyRequestDecision($allocation): int
    {
        $status = $allocation->status ?? null;
        if (! $status) {
            return 0;
        }

        // Terminal statuses only
        $isApproved = in_array($status, ['approved', 'received']);
        $isRejected = $status === 'rejected';

        if (! $isApproved && ! $isRejected) {
            return 0;
        }

        // Requesting RHU
        $rhuId = $allocation->health_unit_id ?? null;
        if (! $rhuId) {
            return 0;
        }

        // Requester (midwife who submitted it)
        $requesterId = $allocation->allocated_by ?? null;

        $qty = $allocation->quantity_allocated
            ?? $allocation->quantity
            ?? 0;

        $medicineName = optional($allocation->medicine)->medicine_name
                     ?? 'medicine';

        $verb = $isApproved ? 'approved' : 'rejected';
        $title = $isApproved ? 'Supply Request Approved' : 'Supply Request Rejected';
        $severity = $isApproved ? 'info' : 'danger';
        $message = "Your request for {$qty} units of {$medicineName} has been {$verb}.";
        $rhuName = $allocation->healthUnit?->name ?? 'the requesting RHU';
        $sharedMessage = "The supply request for {$qty} units of {$medicineName} at {$rhuName} has been {$verb}.";

        $dedupKey = 'supply_req_'.($allocation->id ?? 'x').'_'.$status;
        $link = route('supply_requests.index');

        $count = 0;

        // 1. Shared copy — visible to admin/staff of the requesting RHU
        if ($this->notifyRhu(
            $rhuId,
            'supply_request_'.$verb,
            $title,
            $sharedMessage,
            $severity,
            route('allocation.index'),
            ['allocation_id' => $allocation->id],
            $dedupKey,
            72
        )) {
            $count++;
        }

        // 2. Personal copy — only for the midwife who submitted it
        if ($requesterId) {
            $isMidwife = DB::table('users')
                ->where('id', $requesterId)
                ->where('role', 'midwife')
                ->exists();

            if ($isMidwife) {
                if ($this->notifyUser(
                    (int) $requesterId,
                    $rhuId,
                    'supply_request_'.$verb,
                    $title,
                    $message,
                    $severity,
                    $link,
                    ['allocation_id' => $allocation->id],
                    $dedupKey,
                    72
                )) {
                    $count++;
                }
            }
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════
    // INTERNAL — inventory notification (RHU-shared, private)
    // ═══════════════════════════════════════════════════════════

    private function createInventoryNotification($inventory, string $type, string $title, string $message, string $severity): int
    {
        $existing = Notification::where('inventory_id', $inventory->id)
            ->where('type', $type)
            ->where('severity', $severity)
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();

        if ($existing) {
            return 0;
        }

        Notification::create([
            'health_unit_id' => $inventory->health_unit_id,
            'user_id' => null,
            'inventory_id' => $inventory->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'is_read' => false,
            'created_at' => now(),
        ]);

        return 1;
    }

    // ═══════════════════════════════════════════════════════════
    // READ / ARCHIVE / DELETE — viewer-aware
    // ═══════════════════════════════════════════════════════════

    public function getUnreadCount(?int $healthUnitId, ?int $userId, ?string $role): int
    {
        $q = Notification::unread()->whereNull('archived_at');

        return $this->scopeForViewer($q, $healthUnitId, $userId, $role)->count();
    }

    public function getRecentNotifications(?int $healthUnitId, ?int $userId, ?string $role, int $limit = 20)
    {
        $q = Notification::with(['inventory.medicine', 'healthUnit'])
            ->whereNull('archived_at')
            ->orderByDesc('created_at');

        return $this->scopeForViewer($q, $healthUnitId, $userId, $role)
            ->limit($limit)
            ->get();
    }

    public function getNotifications(?int $healthUnitId, ?int $userId, ?string $role, string $view = 'active', int $limit = 50)
    {
        $q = Notification::with(['inventory.medicine', 'healthUnit', 'transfer', 'allocation']);

        $this->scopeForViewer($q, $healthUnitId, $userId, $role);

        if ($view === 'archived') {
            $q->whereNotNull('archived_at');
        } elseif ($view === 'active') {
            $q->whereNull('archived_at');
        }

        return $q->orderByDesc('created_at')->orderByDesc('id')->paginate($limit)->withQueryString();
    }

    public function getArchivedCount(?int $healthUnitId, ?int $userId, ?string $role): int
    {
        $q = Notification::whereNotNull('archived_at');

        return $this->scopeForViewer($q, $healthUnitId, $userId, $role)->count();
    }

    public function markAsRead(int $notificationId, ?int $healthUnitId, ?int $userId, ?string $role): bool
    {
        $q = Notification::where('id', $notificationId);
        $this->scopeForViewer($q, $healthUnitId, $userId, $role);

        return (bool) $q->update(['is_read' => true]);
    }

    public function markAllAsRead(?int $healthUnitId, ?int $userId, ?string $role): int
    {
        $q = Notification::unread()->whereNull('archived_at');
        $this->scopeForViewer($q, $healthUnitId, $userId, $role);

        return $q->update(['is_read' => true]);
    }

    public function archive(int $id, ?int $healthUnitId, ?int $userId, ?string $role): bool
    {
        $q = Notification::where('id', $id);
        $this->scopeForViewer($q, $healthUnitId, $userId, $role);

        return $q->update(['archived_at' => now()]) > 0;
    }

    public function unarchive(int $id, ?int $healthUnitId, ?int $userId, ?string $role): bool
    {
        $q = Notification::where('id', $id);
        $this->scopeForViewer($q, $healthUnitId, $userId, $role);

        return $q->update(['archived_at' => null]) > 0;
    }

    public function destroyArchived(?int $healthUnitId, ?int $userId, ?string $role): int
    {
        $q = Notification::whereNotNull('archived_at');
        $this->scopeForViewer($q, $healthUnitId, $userId, $role);

        return $q->delete();
    }

    public function destroyById(int $id, ?int $healthUnitId, ?int $userId, ?string $role): bool
    {
        $q = Notification::where('id', $id);
        $this->scopeForViewer($q, $healthUnitId, $userId, $role);

        return $q->delete() > 0;
    }

    public function destroyAll(?int $healthUnitId, ?int $userId, ?string $role): int
    {
        $q = Notification::whereNull('archived_at');
        $this->scopeForViewer($q, $healthUnitId, $userId, $role);

        return $q->delete();
    }
}
