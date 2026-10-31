<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\StockLog;
use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Helpers\ActivityLogger;
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
            ->orderBy('medicine_name', 'asc');

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

        $search       = $request->search;
        $category     = $request->category;
        $rhuFilter    = $request->rhu_filter;
        $availability = $request->availability;
        $healthUnitId = session('health_unit_id');

        $targetUnitId = $healthUnitId ?: ($rhuFilter ?: null);

        $baseQuery = Medicine::query()
            ->when($search, function ($q, $s) {
                $q->where('medicine_name', 'like', "%{$s}%");
            })
            ->when($category, function ($q, $category) {
                $q->where('category', $category);
            })
            ->when($availability === 'available', function ($q) use ($targetUnitId) {
                $q->whereHas('inventories', function ($sub) use ($targetUnitId) {
                    if ($targetUnitId) $sub->where('health_unit_id', $targetUnitId);
                    $sub->where('available_stock', '>', 0);
                });
            })
            ->when($availability === 'unavailable', function ($q) use ($targetUnitId) {
                $q->whereDoesntHave('inventories', function ($sub) use ($targetUnitId) {
                    if ($targetUnitId) $sub->where('health_unit_id', $targetUnitId);
                    $sub->where('available_stock', '>', 0);
                });
            });

        $grandAvailable = (int) Inventory::query()
            ->whereIn('medicine_id', (clone $baseQuery)->select('id'))
            ->when($targetUnitId, fn ($q) => $q->where('health_unit_id', $targetUnitId))
            ->sum('available_stock');

        $grandAllocated = (int) Inventory::query()
            ->whereIn('medicine_id', (clone $baseQuery)->select('id'))
            ->when($targetUnitId, fn ($q) => $q->where('health_unit_id', $targetUnitId))
            ->sum('allocated_stock');

        $items = (clone $baseQuery)
            ->withSum(['inventories as available_total' => function ($q) use ($targetUnitId) {
                if ($targetUnitId) $q->where('health_unit_id', $targetUnitId);
            }], 'available_stock')
            ->withSum(['inventories as allocated_total' => function ($q) use ($targetUnitId) {
                if ($targetUnitId) $q->where('health_unit_id', $targetUnitId);
            }], 'allocated_stock')
            ->withCount(['inventories as rhus_count' => function ($q) use ($targetUnitId) {
                $q->where('available_stock', '>', 0);
                if ($targetUnitId) $q->where('health_unit_id', $targetUnitId);
            }])
            ->orderBy('medicine_name', 'asc')
            ->paginate(20)
            ->appends([
                'search'       => $search,
                'category'     => $category,
                'rhu_filter'   => $rhuFilter,
                'availability' => $availability,
            ]);

        $items->getCollection()->transform(function ($item) {
            $item->available_total = (int) ($item->available_total ?? 0);
            $item->allocated_total = (int) ($item->allocated_total ?? 0);
            $item->rhus_count      = (int) ($item->rhus_count ?? 0);
            return $item;
        });

        $healthUnits = HealthUnit::rhuOnly()->orderBy('name')->get();

        return view('medicine.stock_on_hand', compact(
            'items',
            'healthUnits',
            'search',
            'category',
            'rhuFilter',
            'availability',
            'grandAvailable',
            'grandAllocated'
        ));
    }

    public function stockOnHandCsv(Request $request)
    {
        if (!session()->has('user_id')) {
            return redirect()->route('login');
        }

        $search       = $request->search;
        $category     = $request->category;
        $rhuFilter    = $request->rhu_filter;
        $availability = $request->availability;
        $healthUnitId = session('health_unit_id');

        $targetUnitId = $healthUnitId ?: ($rhuFilter ?: null);

        $items = Medicine::query()
            ->when($search, function ($q, $s) {
                $q->where('medicine_name', 'like', "%{$s}%");
            })
            ->when($category, function ($q, $category) {
                $q->where('category', $category);
            })
            ->when($availability === 'available', function ($q) use ($targetUnitId) {
                $q->whereHas('inventories', function ($sub) use ($targetUnitId) {
                    if ($targetUnitId) $sub->where('health_unit_id', $targetUnitId);
                    $sub->where('available_stock', '>', 0);
                });
            })
            ->when($availability === 'unavailable', function ($q) use ($targetUnitId) {
                $q->whereDoesntHave('inventories', function ($sub) use ($targetUnitId) {
                    if ($targetUnitId) $sub->where('health_unit_id', $targetUnitId);
                    $sub->where('available_stock', '>', 0);
                });
            })
            ->withSum(['inventories as available_total' => function ($q) use ($targetUnitId) {
                if ($targetUnitId) $q->where('health_unit_id', $targetUnitId);
            }], 'available_stock')
            ->withSum(['inventories as allocated_total' => function ($q) use ($targetUnitId) {
                if ($targetUnitId) $q->where('health_unit_id', $targetUnitId);
            }], 'allocated_stock')
            ->withCount(['inventories as rhus_count' => function ($q) use ($targetUnitId) {
                $q->where('available_stock', '>', 0);
                if ($targetUnitId) $q->where('health_unit_id', $targetUnitId);
            }])
            ->orderBy('medicine_name', 'asc')
            ->get();

        $items->transform(function ($item) {
            $item->available_total = (int) ($item->available_total ?? 0);
            $item->allocated_total = (int) ($item->allocated_total ?? 0);
            $item->rhus_count      = (int) ($item->rhus_count ?? 0);
            return $item;
        });

        $filename = 'stock_on_hand_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($items) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
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
                fputcsv($handle, [
                    $item->medicine_name ?? '',
                    $item->category ?? '',
                    $item->dosage ?? '',
                    $item->dosage_form ?? '',
                    $item->unit ?? '',
                    $item->rhus_count,
                    $item->available_total,
                    $item->allocated_total,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function create()
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        $categories     = self::CATEGORIES;
        $categoryLabels = self::CATEGORY_LABELS;
        return view('medicine.create', compact('categories', 'categoryLabels'));
    }

    public function store(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

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
            'requires_prescription' => $request->has('requires_prescription') ? 1 : 0,
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
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        $medicine       = Medicine::findOrFail($id);
        $categories     = self::CATEGORIES;
        $categoryLabels = self::CATEGORY_LABELS;
        return view('medicine.edit', compact('medicine', 'categories', 'categoryLabels'));
    }

    public function update(Request $request, $id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

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
        $medicine->update([
            'medicine_name'         => $request->medicine_name,
            'description'           => $request->description,
            'dosage'                => $request->dosage,
            'category'              => $request->category,
            'dosage_form'           => $request->dosage_form,
            'unit'                  => $request->unit,
            'pack_type'             => $request->pack_type,
            'pieces_per_pack'       => $request->pieces_per_pack,
            'requires_prescription' => $request->has('requires_prescription') ? 1 : 0,
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
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        if (session('health_unit_id') !== null) {
            return redirect()->route('medicine.index')
                ->with('error', 'Only the Provincial Health Office administrator can delete items.');
        }

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

            fputcsv($handle, [
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
                fputcsv($handle, [
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