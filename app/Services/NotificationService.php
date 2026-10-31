<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\Inventory;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationService
{
    // ═══════════════════════════════════════════════════════════
    // VIEWER CONTEXT — routes every read query by role
    //   midwife       → only rows where user_id = her id
    //   admin / staff → only rows where user_id IS NULL
    // ═══════════════════════════════════════════════════════════

    private function scopeForViewer($query, ?int $healthUnitId, ?int $userId, ?string $role)
    {
        if ($role === 'midwife') {
            $query->where('user_id', $userId);
        } else {
            $query->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId));
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
                $daysLeft = now()->diffInDays($inventory->expiration_date, false);
                $count += $this->createInventoryNotification(
                    $inventory,
                    'near_expiry',
                    'Near Expiry Alert',
                    $inventory->medicine->medicine_name.' (Batch: '.($inventory->batch_no ?? 'N/A').') expires in '.$daysLeft.' days. ('.$inventory->expiration_date->format('Y-m-d').')',
                    'warning'
                );
            }

            if ($inventory->available_stock <= 10) {
                $critical = $inventory->available_stock <= 3;
                $count += $this->createInventoryNotification($inventory, 'low_stock',
                    $critical ? 'Critical Low Stock Alert' : 'Low Stock Alert',
                    $inventory->medicine->medicine_name.' has '.$inventory->available_stock.' units remaining.',
                    $critical ? 'danger' : 'warning');
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
        $rows = DB::table('immunization_schedule as s')
            ->leftJoin('immunization_records as r', function ($j) {
                $j->on('r.patient_id', '=', 's.patient_id')
                    ->on('r.vaccine_name', '=', 's.vaccine_name')
                    ->on('r.dose_number', '=', 's.dose_number');
            })
            ->whereNull('r.id')
            ->where('s.scheduled_date', '<', now()->startOfDay())
            ->where('s.scheduled_date', '>=', now()->subDays($lookbackDays)->startOfDay())
            ->whereNotNull('s.health_unit_id')
            ->groupBy('s.health_unit_id')
            ->select('s.health_unit_id', DB::raw('COUNT(*) as overdue_count'))
            ->get();

        $created = 0;

        foreach ($rows as $row) {
            $midwives = DB::table('users')
                ->where('health_unit_id', $row->health_unit_id)
                ->where('role', 'midwife')
                ->pluck('id');

            foreach ($midwives as $midwifeUserId) {
                $n = $this->notifyUser(
                    (int) $midwifeUserId,
                    $row->health_unit_id,
                    'overdue_doses',
                    'Overdue Vaccine Doses',
                    $row->overdue_count.' child(ren) in your RHU have overdue vaccine doses. Open the Immunization TCL to follow up.',
                    'warning',
                    route('immunization.tcl'),
                    [],
                    'overdue_digest',
                    20
                );
                if ($n) {
                    $created++;
                }
            }
        }

        return $created;
    }

    // ═══════════════════════════════════════════════════════════
    // TRIGGER #3 — Low stock digest (nightly, per-RHU)
    // ═══════════════════════════════════════════════════════════

    public function scanLowStockDigest(int $threshold = 10): int
    {
        $rows = DB::table('inventory as i')
            ->join('medicines as m', 'm.id', '=', 'i.medicine_id')
            ->where('i.available_stock', '>', 0)
            ->where('i.available_stock', '<=', $threshold)
            ->whereNotNull('i.health_unit_id')
            ->groupBy('i.health_unit_id')
            ->select(
                'i.health_unit_id',
                DB::raw('COUNT(DISTINCT i.medicine_id) as low_count')
            )
            ->get();

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
            ->where(function ($q) use ($hasType, $hasEventType) {
                $q->where('title', 'like', '%EPI%');
                if ($hasType) {
                    $q->orWhere('type', 'epi');
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

        $dedupKey = 'supply_req_'.($allocation->id ?? 'x').'_'.$status;
        $link = route('supply_requests.index');

        $count = 0;

        // 1. Shared copy — visible to admin/staff of the requesting RHU
        if ($this->notifyRhu(
            $rhuId,
            'supply_request_'.$verb,
            $title,
            $message,
            $severity,
            $link,
            [],
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
                    [],
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
    // TRIGGER #5 — Disease outbreak flagged (called from controller)
    // ═══════════════════════════════════════════════════════════

    public function notifyDiseaseOutbreak($report): int
    {
        $rhuId = $report->health_unit_id ?? null;
        if (! $rhuId) {
            return 0;
        }

        $diseaseName = $report->disease_name
                    ?? optional($report->disease)->name
                    ?? optional($report->disease)->disease_name
                    ?? 'Unknown disease';

        $caseCount = $report->case_count
                  ?? $report->cases
                  ?? $report->total_cases
                  ?? null;

        $caseText = $caseCount
            ? ' ('.$caseCount.' case'.($caseCount == 1 ? '' : 's').')'
            : '';

        $title = 'Disease Outbreak Flagged';
        $message = "A disease outbreak has been flagged at your RHU: {$diseaseName}{$caseText}. Follow-up required.";

        $dedupKey = 'outbreak_'.($report->id ?? 'x');

        $count = 0;

        if ($this->notifyRhu(
            $rhuId,
            'disease_outbreak',
            $title,
            $message,
            'danger',
            route('disease_reports.index'),
            [],
            $dedupKey,
            72
        )) {
            $count++;
        }

        $midwives = DB::table('users')
            ->where('health_unit_id', $rhuId)
            ->where('role', 'midwife')
            ->pluck('id');

        foreach ($midwives as $midwifeUserId) {
            if ($this->notifyUser(
                (int) $midwifeUserId,
                $rhuId,
                'disease_outbreak',
                $title,
                $message,
                'danger',
                route('disease_reports.index'),
                [],
                $dedupKey,
                72
            )) {
                $count++;
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
        $q = Notification::with(['inventory.medicine', 'healthUnit']);

        $this->scopeForViewer($q, $healthUnitId, $userId, $role);

        if ($view === 'archived') {
            $q->whereNotNull('archived_at');
        } elseif ($view === 'active') {
            $q->whereNull('archived_at');
        }

        return $q->orderByDesc('created_at')->limit($limit)->get();
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
