<?php

namespace App\Http\Controllers;

use App\Helpers\ActivityLogger;
use App\Models\Allocation;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplyRequestController extends Controller
{
    private function guardRhuUser()
    {
        if (! session('user_id')) {
            return redirect()->route('login');
        }
        if (is_null(session('health_unit_id'))) {
            return redirect()->route('dashboard')
                ->with('error', 'Supply requests are only available at the RHU level.');
        }

        return null;
    }

    public function index(Request $request)
    {
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');
        $userId = session('user_id');

        $status = $request->input('status');
        $search = $request->input('search');

        $requests = Allocation::with(['medicine'])
            ->where('health_unit_id', $healthUnitId)
            ->where('allocated_by', $userId)
            ->whereNull('destination_health_unit_id')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, function ($q) use ($search) {
                $q->whereHas('medicine', function ($mq) use ($search) {
                    $mq->where('medicine_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->appends(['status' => $status, 'search' => $search]);

        $base = Allocation::where('health_unit_id', $healthUnitId)
            ->where('allocated_by', $userId)
            ->whereNull('destination_health_unit_id');

        $counts = [
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'received' => (clone $base)->where('status', 'received')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
        ];

        return view('supply_requests.index', compact('requests', 'counts', 'status', 'search'));
    }

    public function create(Request $request)
    {
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');
        $medicines = Medicine::orderBy('medicine_name')->get();

        $stockLevels = Inventory::where('health_unit_id', $healthUnitId)
            ->where('available_stock', '>', 0)
            ->get()
            ->groupBy('medicine_id')
            ->map(function ($batches) {
                $nearExpiry = $batches
                    ->filter(fn ($b) => $b->expiration_date &&
                        $b->expiration_date >= now() &&
                        $b->expiration_date <= now()->addMonths(3)
                    )
                    ->sortBy('expiration_date')
                    ->first();

                return [
                    'total' => (int) $batches->sum('available_stock'),
                    'near_expiry' => $nearExpiry ? [
                        'batch_no' => $nearExpiry->batch_no,
                        'date' => $nearExpiry->expiration_date->format('M d, Y'),
                        'qty' => (int) $nearExpiry->available_stock,
                    ] : null,
                ];
            });

        $stockLevels = $stockLevels->map(function ($info, $medicineId) use ($medicines) {
            $med = $medicines->firstWhere('id', $medicineId);
            $info['low'] = $med
                ? $info['total'] <= $med->effectiveMinStock()
                : false;

            return $info;
        });

        $preselectId = $request->input('medicine_id');

        return view('supply_requests.create', compact(
            'medicines', 'stockLevels', 'preselectId'
        ));
    }

    public function store(Request $request)
    {
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');

        $validated = $request->validate([
            'medicine_id' => 'required|exists:medicines,id',
            'quantity' => 'required|integer|min:1',
            'purpose' => 'required|string|max:255',
            'urgency' => 'required|in:normal,urgent,emergency',
        ]);

        $medicine = Medicine::findOrFail($validated['medicine_id']);

        $urgencyTag = $validated['urgency'] !== 'normal'
            ? '['.strtoupper($validated['urgency']).'] '
            : '';

        $allocation = Allocation::create([
            'medicine_id' => $medicine->id,
            'inventory_id' => null,
            'health_unit_id' => $healthUnitId,
            'destination_health_unit_id' => null,
            'destination' => $urgencyTag.$validated['purpose'],
            'quantity_allocated' => $validated['quantity'],
            'date_allocated' => now()->toDateString(),
            'allocated_by' => session('user_id'),
            'status' => 'pending',
        ]);

        // ═══ Notify the RHU admins/staff that a new supply request came in ═══
        $qtyLabel = $validated['quantity'].' '.
            ($validated['quantity'] == 1 ? 'unit' : 'units');

        app(NotificationService::class)->notifyRhu(
            $healthUnitId,
            'supply_request_pending',
            'New Supply Request',
            $medicine->medicine_name.' — '.$qtyLabel.
                ' requested by '.(session('user_name') ?? 'a midwife').'.',
            ($validated['urgency'] === 'emergency' ? 'danger' : 'warning'),
            route('allocation.index'),
            ['allocation_id' => $allocation->id],
            null,   // no dedup — every request should notify
            0
        );
        // ═══════════════════════════════════════════════════════════════════

        ActivityLogger::log(
            'Supply Request',
            'Create',
            'Requested '.$validated['quantity'].' unit(s) of '.$medicine->medicine_name.
            ' for '.$validated['purpose'].
            ($validated['urgency'] !== 'normal' ? ' ('.$validated['urgency'].')' : '').'.'
        );

        return redirect()->route('supply_requests.index')
            ->with('success', 'Request submitted to your RHU admin.');
    }

    public function destroy($id)
    {
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');
        $userId = session('user_id');

        $req = Allocation::where('health_unit_id', $healthUnitId)
            ->where('allocated_by', $userId)
            ->whereNull('destination_health_unit_id')
            ->findOrFail($id);

        $medName = $req->medicine->medicine_name ?? 'medicine';
        $qty = $req->quantity_allocated;
        DB::transaction(function () use ($req) {
            $req = Allocation::whereKey($req->id)->lockForUpdate()->firstOrFail();
            abort_unless($req->status === 'pending' && ! $req->inventory_id, 409);
            $req->delete();
        });

        ActivityLogger::log(
            'Supply Request',
            'Cancel',
            'Cancelled request for '.$qty.' unit(s) of '.$medName.'.'
        );

        return redirect()->route('supply_requests.index')
            ->with('success', 'Request cancelled.');
    }
}
