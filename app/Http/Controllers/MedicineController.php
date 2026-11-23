<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\StockLog;
use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\TransferRequest;
use App\Helpers\ActivityLogger;
use App\Support\SpreadsheetCsv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MedicineController extends Controller
{
    private const CATEGORIES = [
        'medicine',
        'vaccine',
        'family_planning',
        'family_health_nutrition',
        'emerging_reemerging',
        'non_communicable',
        'dengue_prevention',
        'tuberculosis',
    ];

    private const CATEGORY_LABELS = [
        'medicine'                => 'Medicine',
        'vaccine'                 => 'Vaccine',
        'family_planning'         => 'Family Planning',
        'family_health_nutrition' => 'Family Health & Nutrition',
        'emerging_reemerging'     => 'Emerging & Re-emerging',
        'non_communicable'        => 'Non-Communicable Disease',
        'dengue_prevention'       => 'Dengue Prevention',
        'tuberculosis'            => 'Tuberculosis',
    ];

    private function checkAccess()
    {
        if (!session()->has('user_id')) {
            return redirect()->route('login');
        }
        if (!in_array(session('role'), ['admin', 'staff'])) {
            return redirect()->route('login');
        }
        return null;
    }

    private function ensurePhoCatalogEditor(): void
    {
        abort_unless(session('role') === 'admin' && session('health_unit_id') === null, 403,
            'Only a Provincial Health Office administrator can change the item masterlist.');
    }

    public function index(Request $request)
    {
        if (!session()->has('user_id')) {
            return redirect()->route('login');
        }

        $search    = $request->search;
        $rhuFilter = $request->rhu_filter;
        $category  = $request->category;

        $medicines = Medicine::query()
            ->when($search, function ($q, $s) {
                $q->where(function ($sub) use ($s) {
                    $sub->where('medicine_name', 'like', "%{$s}%")
                        ->orWhere('dosage', 'like', "%{$s}%")
                        ->orWhere('category', 'like', "%{$s}%")
                        ->orWhere('dosage_form', 'like', "%{$s}%")
                        ->orWhere('description', 'like', "%{$s}%");
                });
            })
            ->when($category, function ($q, $category) {
                $q->where('category', $category);
            })
            ->orderBy('medicine_name', 'asc')
            ->orderBy('id', 'asc');

        if (is_null(session('health_unit_id'))) {
            $medicines = $medicines->withCount([
                'inventories as rhus_count' => function ($q) use ($rhuFilter) {
                    $q->where('available_stock', '>', 0);
                    if ($rhuFilter) {
                        $q->where('health_unit_id', $rhuFilter);
                    }
                }
            ])->withSum([
                'inventories as total_available' => function ($q) use ($rhuFilter) {
                    if ($rhuFilter) {
                        $q->where('health_unit_id', $rhuFilter);
                    }
                }
            ], 'available_stock');
        }

        $medicines = $medicines->paginate(config('pagination.medicines', 20))
            ->appends(['search' => $search, 'rhu_filter' => $rhuFilter, 'category' => $category]);

        $healthUnits = HealthUnit::rhuOnly()->orderBy('name')->get();

        return view('medicine.index', compact('medicines', 'healthUnits'));
    }

    public function stockOnHand(Request $request)
    {
        if (!session()->has('user_id')) {
            return redirect()->route('login');
        }

        $filters = $this->stockOnHandFilters($request);
        $targetUnitId = session('health_unit_id') ?? ($filters['rhu_filter'] ?? null);
        $baseQuery = $this->stockOnHandQuery($filters, $targetUnitId);
        $batches = $this->stockAtFacility(Inventory::query(), $targetUnitId)
            ->whereIn('medicine_id', (clone $baseQuery)->select('id'));

        $grandAvailable = (int) (clone $batches)->usableStock()->sum('available_stock');
        $grandAllocated = (int) $batches->sum('allocated_stock');
        $availableMedicineCount = (clone $baseQuery)
            ->whereHas('inventories', fn (Builder $query) => $this->stockAtFacility($query, $targetUnitId)->usableStock())
            ->count();
        $items = $this->withStockOnHandBalances($baseQuery, $targetUnitId)
            ->paginate(20)->appends($filters);

        $healthUnits = HealthUnit::rhuOnly()->orderBy('name')->get();

        return view('medicine.stock_on_hand', compact(
            'items',
            'healthUnits',
            'targetUnitId',
            'availableMedicineCount',
            'grandAvailable',
            'grandAllocated'
        ));
    }

    public function stockOnHandCsv(Request $request)
    {
        if (!session()->has('user_id')) {
            return redirect()->route('login');
        }

        $filters = $this->stockOnHandFilters($request);
        $targetUnitId = session('health_unit_id') ?? ($filters['rhu_filter'] ?? null);
        $items = $this->withStockOnHandBalances($this->stockOnHandQuery($filters, $targetUnitId), $targetUnitId)
            ->withAggregate(
                ['inventories as rhus_count' => fn (Builder $query) => $this->stockAtFacility($query, $targetUnitId)
                    ->usableStock()->whereHas('healthUnit', fn (Builder $unit) => $unit->rhuOnly())],
                new Expression('distinct inventory.health_unit_id'),
                'count',
            )->get();

        $filename = 'stock_on_hand_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($items) {
            $handle = fopen('php://output', 'w');

            SpreadsheetCsv::writeRow($handle, [
                'Item Name',
                'Category',
                'Dosage',
                'Dosage Form',
                'Base Unit',
                'RHUs w/ Stock',
                'Available',
                'Allocated',
            ]);

            foreach ($items as $item) {
                SpreadsheetCsv::writeRow($handle, [
                    $item->medicine_name ?? '',
                    $item->category ?? '',
                    $item->dosage ?? '',
                    $item->dosage_form ?? '',
                    $item->unit ?? '',
                    (int) $item->rhus_count,
                    (int) $item->available_total,
                    (int) $item->allocated_total,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function stockOnHandAutocomplete(Request $request)
    {
        $filters = $this->stockOnHandFilters($request);
        if (mb_strlen($filters['search'] ?? '') < 2) {
            return response()->json([]);
        }

        $targetUnitId = session('health_unit_id') ?? ($filters['rhu_filter'] ?? null);

        return response()->json($this->stockOnHandQuery($filters, $targetUnitId)
            ->distinct()->orderBy('medicine_name')->limit(15)->pluck('medicine_name'));
    }

    private function stockOnHandFilters(Request $request): array
    {
        return $request->validate([
            'search' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'dosage_form' => 'nullable|string|max:100',
            'rhu_filter' => 'nullable|integer|min:1',
            'availability' => ['nullable', Rule::in(['available', 'unavailable'])],
        ]);
    }

    private function stockOnHandQuery(array $filters, ?int $targetUnitId): Builder
    {
        $availability = $filters['availability'] ?? null;
        $usableStock = fn (Builder $query) => $this->stockAtFacility($query, $targetUnitId)->usableStock();

        return Medicine::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->byName($search))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->byCategory($category))
            ->when($filters['dosage_form'] ?? null, fn (Builder $query, string $form) => $query->where('dosage_form', $form))
            ->when($availability === 'available', fn (Builder $query) => $query->whereHas('inventories', $usableStock))
            ->when($availability === 'unavailable', fn (Builder $query) => $query->whereDoesntHave('inventories', $usableStock));
    }

    private function withStockOnHandBalances(Builder $query, ?int $targetUnitId): Builder
    {
        return $query
            ->withSum(['inventories as available_total' => fn (Builder $batch) => $this->stockAtFacility($batch, $targetUnitId)->usableStock()], 'available_stock')
            ->withSum(['inventories as allocated_total' => fn (Builder $batch) => $this->stockAtFacility($batch, $targetUnitId)], 'allocated_stock')
            ->orderBy('medicine_name')
            ->orderBy('id');
    }

    private function stockAtFacility(Builder $query, ?int $targetUnitId): Builder
    {
        return $query->when($targetUnitId !== null, fn (Builder $batch) => $batch->where('health_unit_id', $targetUnitId));
    }

    public function create()
    {
        $this->ensurePhoCatalogEditor();
        $categories     = self::CATEGORIES;
        $categoryLabels = self::CATEGORY_LABELS;
        return view('medicine.create', compact('categories', 'categoryLabels'));
    }

    public function store(Request $request)
    {
        $this->ensurePhoCatalogEditor();

        $validated = $request->validate([
            'medicine_name'         => 'required|string|max:150',
            'description'           => 'required|string',
            'dosage'                => 'nullable|string|max:100',
            'category'              => ['required', Rule::in(self::CATEGORIES)],
            'dosage_form'           => 'nullable|string|max:50',
            'unit'                  => 'nullable|string|max:20',
            'pack_type'             => 'nullable|string|max:30',
            'pieces_per_pack'       => 'nullable|integer|min:1',
            'requires_prescription' => 'nullable|boolean',
        ]);

        if ($request->dosage_form !== 'Device' && empty($validated['dosage'])) {
            return back()
                ->withInput()
                ->withErrors(['dosage' => 'Dosage strength is required for this dosage form.']);
        }

        $duplicate = Medicine::where('medicine_name', $request->medicine_name)
            ->where('category', $request->category)
            ->where('dosage_form', $request->dosage_form)
            ->where('dosage', $request->dosage)
            ->exists();

        if ($duplicate) {
            return back()
                ->withInput()
                ->withErrors(['medicine_name' => 'A similar item already exists in this category.']);
        }

        $medicine = Medicine::create([
            'medicine_name'         => $request->medicine_name,
            'description'           => $request->description,
            'dosage'                => $request->dosage,
            'category'              => $request->category,
            'dosage_form'           => $request->dosage_form,
            'unit'                  => $request->unit,
            'pack_type'             => $request->pack_type,
            'pieces_per_pack'       => $request->pieces_per_pack,
            'requires_prescription' => $request->boolean('requires_prescription'),
            'is_dispensable'        => true,
        ]);

        ActivityLogger::log(
            'Medicines',
            'Create',
            'Added item: ' . $medicine->medicine_name .
            ($medicine->dosage_form ? ' (' . $medicine->dosage_form . ')' : '') . '.'
        );

        return redirect()->route('medicine.index')->with('success', 'Item added successfully.');
    }

    public function edit($id)
    {
        $this->ensurePhoCatalogEditor();
        $medicine       = Medicine::findOrFail($id);
        $categories     = self::CATEGORIES;
        $categoryLabels = self::CATEGORY_LABELS;
        return view('medicine.edit', compact('medicine', 'categories', 'categoryLabels'));
    }

    public function update(Request $request, $id)
    {
        $this->ensurePhoCatalogEditor();

        $request->validate([
            'medicine_name'         => 'required|string|max:150',
            'description'           => 'required|string',
            'dosage'                => 'nullable|string|max:100',
            'category'              => ['required', Rule::in(self::CATEGORIES)],
            'dosage_form'           => 'nullable|string|max:50',
            'unit'                  => 'nullable|string|max:20',
            'pack_type'             => 'nullable|string|max:30',
            'pieces_per_pack'       => 'nullable|integer|min:1',
            'requires_prescription' => 'nullable|boolean',
        ]);

        if ($request->dosage_form !== 'Device' && empty($request->dosage)) {
            return back()
                ->withInput()
                ->withErrors(['dosage' => 'Dosage strength is required for this dosage form.']);
        }

        $medicine = Medicine::findOrFail($id);
        $unit = $request->input('unit') ?: $medicine->unit;
        if ($medicine->unit && strcasecmp($medicine->unit, $unit) === 0) {
            $unit = $medicine->unit;
        }
        if ($medicine->unit && $unit !== $medicine->unit
            && ($medicine->inventories()->withTrashed()->exists() || $medicine->stockLogs()->exists()
                || $medicine->allocations()->withTrashed()->exists() || $medicine->dispensingRecords()->withTrashed()->exists()
                || TransferRequest::where('medicine_id', $medicine->id)->exists())) {
            return back()->withInput()->withErrors(['unit' => 'This item has stock history in its current unit. Use a separate catalog item for the new unit.']);
        }

        $medicine->update([
            'medicine_name'         => $request->medicine_name,
            'description'           => $request->description,
            'dosage'                => $request->dosage,
            'category'              => $request->category,
            'dosage_form'           => $request->dosage_form,
            'unit'                  => $unit,
            'pack_type'             => $request->pack_type,
            'pieces_per_pack'       => $request->pieces_per_pack,
            'requires_prescription' => $request->boolean('requires_prescription'),
        ]);

        ActivityLogger::log(
            'Medicines',
            'Update',
            'Updated item: ' . $medicine->medicine_name .
            ($medicine->dosage_form ? ' (' . $medicine->dosage_form . ')' : '') . '.'
        );

        return redirect()->route('medicine.index')->with('success', 'Item updated successfully.');
    }

    public function destroy($id)
    {
        $this->ensurePhoCatalogEditor();

        $medicine = Medicine::findOrFail($id);

        $checks = [
            'inventory record'  => Inventory::where('medicine_id', $medicine->id)->count(),
            'stock log'         => StockLog::where('medicine_id', $medicine->id)->count(),
            'allocation'        => Allocation::where('medicine_id', $medicine->id)->count(),
            'dispensing record' => DispensingRecord::where('medicine_id', $medicine->id)->count(),
        ];

        foreach ($checks as $label => $count) {
            if ($count > 0) {
                return redirect()->route('medicine.index')
                    ->with('error', "Cannot delete this item — it has {$count} {$label}(s) referencing it.");
            }
        }

        $medicine->delete();

        ActivityLogger::log(
            'Medicines',
            'Delete',
            'Deleted item: ' . $medicine->medicine_name . '.'
        );

        return redirect()->route('medicine.index')->with('success', 'Item deleted successfully.');
    }

    public function exportCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $search   = $request->search;
        $category = $request->category;

        $medicines = Medicine::when($search, function ($query, $search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('medicine_name', 'like', "%{$search}%")
                        ->orWhere('dosage', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('dosage_form', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($category, function ($q, $category) {
                $q->where('category', $category);
            })
            ->orderBy('medicine_name', 'asc')
            ->get();

        $filename = 'medicines_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($medicines) {
            $handle = fopen('php://output', 'w');

            SpreadsheetCsv::writeRow($handle, [
                'ID',
                'Item Name',
                'Dosage',
                'Category',
                'Dosage Form',
                'Base Unit',
                'Pack Type',
                'Pieces per Pack',
                'Requires Prescription',
                'Description'
            ]);

            foreach ($medicines as $medicine) {
                SpreadsheetCsv::writeRow($handle, [
                    $medicine->id,
                    $medicine->medicine_name,
                    $medicine->dosage ?? '',
                    $medicine->category,
                    $medicine->dosage_form ?? '',
                    $medicine->unit ?? '',
                    $medicine->pack_type ?? '',
                    $medicine->pieces_per_pack ?? '',
                    $medicine->requires_prescription ? 'Yes' : 'No',
                    $medicine->description,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
