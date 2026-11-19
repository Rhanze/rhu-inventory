<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\ImmunizationSchedule;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\PublicUser;
use App\Models\StockLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(bool $pho = false): array
    {
        $unit = HealthUnit::create(['name' => 'CSV RHU', 'facility_type' => 'Rural Health Unit']);
        $source = HealthUnit::create(['name' => 'CSV Source', 'facility_type' => 'Rural Health Unit']);
        $warehouse = HealthUnit::create(['name' => 'CSV PHO', 'facility_type' => 'Provincial Health Office']);
        $admin = User::factory()->create(['role' => 'admin', 'health_unit_id' => $pho ? null : $unit->id]);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'CSV medicine', 'category' => 'medicine',
            'description' => 'CSV fixture', 'unit' => 'tablet', 'requires_prescription' => false]);
        $inventory = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'available_stock' => 100, 'allocated_stock' => 0, 'batch_no' => 'CSV-BATCH', 'expiration_date' => today()->addMonth()]);
        foreach ([$source, $warehouse] as $location) {
            Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $location->id,
                'available_stock' => 100, 'allocated_stock' => 0, 'expiration_date' => today()->addMonth()]);
        }
        $patient = PublicUser::create(['first_name' => 'CSV', 'last_name' => 'Patient', 'birth_date' => '2000-01-01',
            'health_unit_id' => $unit->id, 'assigned_midwife_id' => $midwife->id, 'barangay' => 'CSV Area', 'address' => 'CSV street']);
        ImmunizationSchedule::create(['antigen' => 'CSVV', 'antigen_label' => 'CSV Vaccine', 'dose_number' => 1,
            'min_age_days' => 0, 'recommended_age_label' => 'Birth', 'is_active' => true]);
        $this->withSession(['user_id' => $admin->id]);

        return compact('unit', 'source', 'warehouse', 'admin', 'midwife', 'medicine', 'inventory', 'patient');
    }

    private function upload(string $section, array $rows, ?string $token = null)
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, array_keys($rows[0]), ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_values($row), ',', '"', '');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return $this->post(route('imports.store', ['section' => $section]), [
            'file' => UploadedFile::fake()->createWithContent('import.csv', $contents),
            'import_token' => $token ?? (string) Str::uuid(),
        ]);
    }

    public static function activeSections(): array
    {
        return [
            'medicines' => ['medicines', false, 'medicines', ['medicine_name' => 'Imported item', 'description' => 'Imported description', 'dosage' => '10 mg', 'category' => 'medicine']],
            'inventory' => ['inventory', false, 'inventory', ['medicine' => 'CSV medicine', 'available_stock' => 10, 'allocated_stock' => 0, 'date_added' => '2026-01-02', 'remarks' => 'Opening stock']],
            'stock additions' => ['stock_logs', false, 'stock_logs', ['medicine' => 'CSV medicine', 'quantity_added' => 10, 'date_added' => '2026-01-02', 'remarks' => 'Delivery']],
            'allocations' => ['allocations', false, 'allocations', ['medicine' => 'CSV medicine', 'destination' => 'Clinic', 'quantity_allocated' => 3, 'date_allocated' => '2026-01-02']],
            'provincial allocations' => ['provincial_allocations', true, 'allocations', ['medicine' => 'CSV medicine', 'destination_rhu' => 'CSV RHU', 'quantity_allocated' => 3, 'date_allocated' => '2026-01-02']],
            'dispensing' => ['dispensing', false, 'dispensing_records', ['medicine' => 'CSV medicine', 'quantity_dispensed' => 3, 'date_dispensed' => '2026-01-02']],
            'rhus' => ['rhus', true, 'health_units', ['name' => 'Imported RHU', 'municipality' => 'San Jose', 'address' => 'Imported address']],
            'patients' => ['patients', false, 'public_users', ['first_name' => 'Imported', 'last_name' => 'Patient', 'birth_date' => '2000-01-01', 'barangay' => 'CSV Area', 'address' => 'Imported address']],
            'prenatal' => ['prenatal', false, 'prenatal_visits', ['patient' => 'CSV Patient', 'visit_date' => '2026-01-02', 'trimester' => '1st']],
            'postpartum' => ['postpartum', false, 'postpartum_visits', ['patient' => 'CSV Patient', 'delivery_date' => '2026-01-01', 'visit_date' => '2026-01-02']],
            'family planning' => ['family_planning', false, 'family_planning', ['patient' => 'CSV Patient', 'method' => 'Pills', 'date_accepted' => '2026-01-02']],
            'immunization' => ['immunization', false, 'immunization_records', ['patient' => 'CSV Patient', 'vaccine' => 'CSVV', 'dose_number' => 1, 'date_given' => '2026-01-02']],
            'supply requests' => ['supply_requests', false, 'allocations', ['medicine' => 'CSV medicine', 'quantity' => 3, 'purpose' => 'Clinic replenishment', 'urgency' => 'normal']],
            'transfer requests' => ['transfers', false, 'transfer_requests', ['source_rhu' => 'CSV Source', 'medicine' => 'CSV medicine', 'quantity' => 3]],
            'distribution' => ['distribution', true, 'transfer_requests', ['source_rhu' => 'CSV Source', 'destination_rhu' => 'CSV RHU', 'medicine' => 'CSV medicine', 'quantity' => 3]],
            'shipments' => ['shipments', true, 'doh_shipments', ['shipment_no' => 'CSV-001', 'received_date' => '2026-01-02', 'medicine' => 'CSV medicine', 'quantity' => 3]],
            'calendar events' => ['calendar_events', false, 'calendar_events', ['title' => 'CSV clinic', 'event_date' => '2026-01-02', 'type' => 'other', 'priority' => 'normal']],
            'users' => ['users', false, 'users', ['name' => 'Imported staff', 'username' => 'csv-imported-staff', 'role' => 'staff', 'rhu' => 'CSV RHU', 'password' => 'CsvPass123!', 'password_confirmation' => 'CsvPass123!']],
            'historical activities' => ['activity_logs', false, 'activity_logs', ['source_user' => 'Legacy operator', 'source_module' => 'Inventory', 'source_action' => 'Create', 'source_description' => 'Archived receipt', 'source_date' => '2019-01-02', 'source_location' => 'CSV RHU']],
        ];
    }

    #[DataProvider('activeSections')]
    public function test_each_active_section_imports_through_its_normal_workflow(string $section, bool $pho, string $table, array $row): void
    {
        $this->fixture($pho);
        $before = DB::table($table)->count();
        $this->get(route('imports.create', ['section' => $section]))->assertOk()->assertSee('Download template');
        $template = $this->get(route('imports.template', ['section' => $section]))->assertOk();
        $this->assertStringContainsString(array_key_first($row), $template->streamedContent());

        $this->upload($section, [$row])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseCount($table, $before + ($section === 'activity_logs' ? 2 : 1));
        $this->assertDatabaseHas('csv_import_batches', ['section' => $section, 'row_count' => 1]);
        if ($section === 'users') {
            $imported = User::where('username', 'csv-imported-staff')->firstOrFail();
            $this->assertTrue(Hash::check('CsvPass123!', $imported->password));
            $this->assertStringNotContainsString('CsvPass123!', serialize(session()->all()));
        }
        if ($section === 'inventory') {
            $log = StockLog::where('remarks', 'like', '%Opening stock%')->firstOrFail();
            $this->assertSame('2026-01-02', $log->date_added->toDateString());
        }
    }

    public function test_a_later_invalid_row_rolls_back_stock_and_audit_rows_from_the_entire_file(): void
    {
        $fixture = $this->fixture();
        $before = Inventory::count();
        $this->upload('inventory', [
            ['medicine' => 'CSV medicine', 'available_stock' => 4, 'allocated_stock' => 0],
            ['medicine' => 'CSV medicine', 'available_stock' => -2, 'allocated_stock' => 0],
        ])->assertSessionHasErrors(['file' => 'Row 3 — The available stock field must be at least 0.']);

        $this->assertDatabaseCount('inventory', $before);
        $this->assertDatabaseCount('stock_logs', 0);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertDatabaseCount('csv_import_batches', 0);
        $this->assertSame(100, $fixture['inventory']->fresh()->available_stock);
    }

    public function test_rhu_admin_cannot_import_epi_day_events(): void
    {
        $this->fixture();
        $this->upload('calendar_events', [
            ['title' => 'Ordinary clinic', 'event_date' => today()->toDateString(), 'type' => 'other', 'priority' => 'normal'],
            ['title' => 'EPI day', 'event_date' => today()->toDateString(), 'type' => 'immunization_day', 'priority' => 'normal'],
        ])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('calendar_events', 0);
    }

    public function test_replaying_the_same_import_post_does_not_repeat_stock_additions(): void
    {
        $fixture = $this->fixture();
        $token = (string) Str::uuid();
        $row = ['medicine' => 'CSV medicine', 'quantity_added' => 7, 'date_added' => today()->toDateString(), 'batch_no' => 'CSV-BATCH', 'expiration_date' => today()->addMonth()->toDateString()];
        $this->upload('stock_logs', [$row], $token)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->upload('stock_logs', [$row], $token)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(107, (int) Inventory::where('health_unit_id', $fixture['unit']->id)->sum('available_stock'));
        $this->assertDatabaseCount('stock_logs', 1);
        $this->assertDatabaseCount('csv_import_batches', 1);
        $this->assertSame(1, DB::table('activity_logs')->where('module', 'CSV Import')->count());
    }

    public function test_existing_export_headers_bom_boolean_labels_and_delimiters_are_supported(): void
    {
        $this->fixture();
        $contents = "\xEF\xBB\xBF#;Item Name;Category;Dosage;Dosage Form;Base Unit;Pack Type;Pieces per Pack;Requires Prescription;Description\r\n1;Imported export;medicine;25 mg;Tablet;tablet;box;10;No;Export description\r\n";
        $this->post(route('imports.store', ['section' => 'medicines']), ['file' => UploadedFile::fake()->createWithContent('export.csv', $contents), 'import_token' => (string) Str::uuid()])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('medicines', ['medicine_name' => 'Imported export', 'requires_prescription' => false, 'pieces_per_pack' => 10]);
    }

    public function test_stock_report_quantities_and_type_labels_resolve_and_releases_are_rejected(): void
    {
        $this->fixture();
        $row = ['ID' => 9, 'Medicine' => 'CSV medicine', 'Type' => 'Stock In', 'Quantity' => '5 tablets', 'Date Added' => '2026-01-02', 'Added By' => 'Legacy user'];
        $this->upload('stock_logs', [$row])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(5, StockLog::sole()->quantity_added);
        $this->upload('stock_logs', [array_replace($row, ['Type' => 'Stock Out'])])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('stock_logs', 1);
        $this->upload('stock_logs', [array_replace($row, ['Type' => 'Stock In', 'Remarks' => 'Released — Dispensed'])])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('stock_logs', 1);
    }

    public function test_actual_inventory_and_stock_report_exports_can_feed_the_owning_import(): void
    {
        $fixture = $this->fixture();
        $fixture['medicine']->update(['pack_type' => 'box', 'pieces_per_pack' => 10]);
        $inventoryCsv = $this->get(route('reports.inventory.csv'))->assertOk()->streamedContent();
        $this->post(route('imports.store', ['section' => 'inventory']), [
            'file' => UploadedFile::fake()->createWithContent('inventory-report.csv', $inventoryCsv),
            'import_token' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $newBatch = Inventory::latest('id')->firstOrFail();
        $this->assertSame('box', $newBatch->pack_type);
        $this->assertSame(10, $newBatch->pieces_per_pack);

        $stockCsv = $this->get(route('reports.stock.csv'))->assertOk()->streamedContent();
        $this->post(route('imports.store', ['section' => 'stock_logs']), [
            'file' => UploadedFile::fake()->createWithContent('stock-report.csv', $stockCsv),
            'import_token' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseCount('stock_logs', 2);
    }

    public function test_explicit_ids_disambiguate_matching_medicine_and_patient_names(): void
    {
        $fixture = $this->fixture();
        Medicine::create(['medicine_name' => 'CSV medicine', 'category' => 'vaccine']);
        PublicUser::create(['first_name' => 'CSV', 'last_name' => 'Patient', 'birth_date' => '2001-01-01', 'health_unit_id' => $fixture['unit']->id, 'address' => 'Duplicate name address']);
        $this->upload('stock_logs', [['medicine' => 'CSV medicine', 'medicine_id' => $fixture['medicine']->id, 'quantity_added' => 2, 'date_added' => '2026-01-02']])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->upload('prenatal', [['patient' => 'CSV Patient', 'patient_id' => $fixture['patient']->id, 'visit_date' => '2026-01-02']])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('prenatal_visits', ['patient_id' => $fixture['patient']->id]);
        $this->upload('stock_logs', [['medicine' => 'CSV medicine', 'quantity_added' => 2, 'date_added' => '2026-01-02']])->assertSessionHasErrors('file');
    }

    public function test_patient_and_stock_references_cannot_cross_rhu_boundaries(): void
    {
        $fixture = $this->fixture();
        $foreignPatient = PublicUser::create(['first_name' => 'Other', 'last_name' => 'Patient', 'birth_date' => '2001-01-01', 'health_unit_id' => $fixture['source']->id, 'address' => 'Other RHU address']);
        $foreignBatch = Inventory::where('health_unit_id', $fixture['source']->id)->firstOrFail();
        $this->upload('prenatal', [['patient_id' => $foreignPatient->id, 'visit_date' => '2026-01-02']])->assertSessionHasErrors('file');
        $this->upload('dispensing', [['inventory_id' => $foreignBatch->id, 'quantity_dispensed' => 2, 'date_dispensed' => '2026-01-02']])->assertSessionHasErrors('file');
        $this->upload('inventory', [['medicine' => 'CSV medicine', 'available_stock' => 2, 'allocated_stock' => 0, 'RHU' => 'CSV Source']])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('prenatal_visits', 0);
        $this->assertDatabaseCount('dispensing_records', 0);
        $this->assertSame(100, $foreignBatch->fresh()->available_stock);
    }

    public function test_midwife_can_import_dispensing_from_received_allocations_with_no_shared_stock(): void
    {
        $fixture = $this->fixture();
        $this->post(route('allocation.store'), ['inventory_id' => $fixture['inventory']->id, 'recipient_user_id' => $fixture['midwife']->id,
            'destination' => 'CSV Area', 'quantity_allocated' => 100, 'date_allocated' => today()->toDateString()])->assertSessionHas('success');
        $allocation = Allocation::sole();
        $this->withSession(['user_id' => $fixture['midwife']->id])->post(route('allocation.mark_received', $allocation->id))->assertSessionHas('success');
        $this->assertSame(0, $fixture['inventory']->fresh()->available_stock);

        $this->upload('dispensing', [['medicine' => 'CSV medicine', 'quantity_dispensed' => 8, 'date_dispensed' => today()->toDateString(), 'stock_source' => 'allocation']])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('allocation', DispensingRecord::sole()->stock_source);
        $this->assertSame(92, $fixture['inventory']->fresh()->allocated_stock);
    }

    public function test_shipments_group_item_rows_and_keep_item_row_errors_atomic(): void
    {
        $this->fixture(true);
        $row = ['shipment_no' => 'CSV-SHIPMENT', 'received_date' => '2026-01-02', 'medicine' => 'CSV medicine', 'quantity' => 3];
        $this->upload('shipments', [$row, array_replace($row, ['quantity' => 4])])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseCount('doh_shipments', 1);
        $this->assertDatabaseCount('doh_shipment_items', 2);
        $this->upload('shipments', [array_replace($row, ['shipment_no' => 'CSV-FAILED']), array_replace($row, ['shipment_no' => 'CSV-FAILED', 'quantity' => 0])])
            ->assertSessionHasErrors(['file' => 'Row 3 — Quantity must be at least 1.']);
        $this->assertDatabaseCount('doh_shipments', 1);
        $this->assertDatabaseCount('doh_shipment_items', 2);
    }

    public function test_role_checks_protect_templates_forms_and_uploads_and_unknown_columns_are_rejected(): void
    {
        $fixture = $this->fixture();
        $this->withSession(['user_id' => $fixture['midwife']->id]);
        $this->get(route('imports.create', ['section' => 'inventory']))->assertForbidden();
        $this->get(route('imports.template', ['section' => 'users']))->assertForbidden();
        $this->upload('medicines', [['medicine_name' => 'Forbidden']])->assertForbidden();
        $this->get(route('imports.create', ['section' => 'notifications']))->assertNotFound();
        $this->get(route('imports.create', ['section' => 'medicines.roles']))->assertNotFound();
        $this->withSession(['user_id' => $fixture['admin']->id]);
        $this->upload('inventory', [['medicine' => 'CSV medicine', 'available_stock' => 2, 'allocated_stock' => 0, 'is_emergency' => 1]])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('csv_import_batches', 0);
    }

    public function test_imported_notifications_keep_the_xampp_subdirectory_in_their_links(): void
    {
        $fixture = $this->fixture();
        $this->withServerVariables(['SCRIPT_NAME' => '/rhu-inventory/public/index.php',
            'PHP_SELF' => '/rhu-inventory/public/index.php', 'SCRIPT_FILENAME' => public_path('index.php')]);
        $this->post('http://localhost/rhu-inventory/public/imports/transfers', [
            'file' => UploadedFile::fake()->createWithContent('transfer.csv', "source_rhu,medicine,quantity\nCSV Source,CSV medicine,3\n"),
            'import_token' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseHas('transfer_requests', ['pho_review_status' => 'pending', 'status' => 'pending']);
        $this->assertDatabaseHas('notifications', ['type' => 'transfer_pho_review_requested',
            'link' => 'http://localhost/rhu-inventory/public/transfers']);
        $this->assertDatabaseMissing('notifications', ['type' => 'transfer_request', 'health_unit_id' => $fixture['source']->id]);
    }

    public function test_historical_activity_exports_retain_source_metadata_and_use_the_current_importer_and_time(): void
    {
        $fixture = $this->fixture();
        $this->upload('activity_logs', [['#' => 1, 'User' => 'Legacy operator', 'Module' => 'Inventory',
            'Action' => 'Delete', 'Description' => 'Archived source description', 'Date & Time' => 'Jan 02, 2019 | 10:00 AM', 'Where' => 'CSV RHU']])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $log = DB::table('activity_logs')->where('module', 'Imported History')->sole();
        $this->assertSame($fixture['admin']->id, (int) $log->user_id);
        $this->assertSame($fixture['unit']->id, (int) $log->health_unit_id);
        $this->assertSame('Import', $log->action);
        $this->assertStringStartsWith(today()->toDateString(), $log->created_at);
        $this->assertStringContainsString('Source actor — Legacy operator', $log->description);
        $this->assertStringContainsString('Source action — Delete', $log->description);
        $this->assertStringContainsString('Source date and time — Jan 02, 2019 | 10:00 AM', $log->description);
    }

    public function test_historical_activity_imports_enforce_scope_and_roll_back_the_whole_file(): void
    {
        $fixture = $this->fixture();
        $row = ['source_user' => 'Legacy operator', 'source_module' => 'Inventory', 'source_action' => 'Create',
            'source_description' => 'Archived receipt', 'source_location' => 'CSV RHU'];
        $this->upload('activity_logs', [$row, array_replace($row, ['source_location' => 'CSV Source'])])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertDatabaseCount('csv_import_batches', 0);
        $this->withSession(['user_id' => $fixture['midwife']->id]);
        $this->upload('activity_logs', [$row])->assertForbidden();
    }

    public function test_read_only_export_users_reach_the_import_hub_without_clinical_write_access(): void
    {
        $this->fixture(true);
        $this->get(route('imports.index'))->assertOk()->assertSee('Clinical, patient, and dispensing imports')
            ->assertSee(route('imports.create', ['section' => 'shipments']), false)
            ->assertDontSee(route('imports.create', ['section' => 'dispensing']), false);
        $this->get(route('imports.create', ['section' => 'dispensing']))->assertForbidden();
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => null]);
        $this->withSession(['user_id' => $staff->id])->get(route('reports.inventory'))->assertOk()
            ->assertSee(route('imports.index'), false)->assertSee('>Import</a>', false)
            ->assertDontSee('Import source data');
    }

    public function test_export_views_include_appropriate_source_import_entry_points(): void
    {
        $fixture = $this->fixture();
        foreach (['medicine.index', 'medicine.stock_on_hand', 'inventory.index', 'stock_logs.index',
            'allocation.index', 'dispensing.index', 'reports.inventory', 'reports.stock', 'reports.allocation',
            'reports.dispensing', 'reports.near_expiry', 'vaccination.monthly',
            'activity_logs.index', 'elmis.export'] as $route) {
            $response = $this->get(route($route))->assertOk()->assertSee('/imports', false);
            $this->assertImportBesideExport($response->getContent(), $route);
        }
        $this->withSession(['user_id' => $fixture['midwife']->id])->get(route('supply_requests.index'))->assertOk()
            ->assertSee(route('imports.create', ['section' => 'supply_requests']), false);

        $pho = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $this->withSession(['user_id' => $pho->id]);
        foreach (['redistribution.index', 'inventory.pho_receive'] as $route) {
            $response = $this->get(route($route))->assertOk()->assertSee('/imports', false);
            $this->assertImportBesideExport($response->getContent(), $route);
        }
    }

    private function assertImportBesideExport(string $html, string $route): void
    {
        $previousErrors = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        $xpath = new \DOMXPath($document);
        $imports = $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " csv-import-link ")]');
        $this->assertGreaterThan(0, $imports->length, $route);
        foreach ($imports as $import) {
            $this->assertSame('Import', trim($import->textContent), $route);
            $exports = $xpath->query('./button[contains(normalize-space(.), "Export")] | ./a[contains(normalize-space(.), "Export")]', $import->parentNode);
            $this->assertGreaterThan(0, $exports->length, $route.' keeps Import beside Export');
        }
    }

    public function test_user_csv_import_accepts_a_quoted_cell_with_one_barangay_per_line(): void
    {
        $this->fixture();
        $this->upload('users', [['name' => 'CSV area midwife', 'username' => 'csv-area-midwife', 'role' => 'midwife',
            'rhu' => 'CSV RHU', 'password' => 'CsvPass123!', 'password_confirmation' => 'CsvPass123!',
            'barangay_names' => "San Jose\nPoblacion", 'primary_barangay' => 'San Jose']])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $midwife = User::where('username', 'csv-area-midwife')->firstOrFail();
        $this->assertSame(['San Jose', 'Poblacion'], $midwife->assignedBarangays()->orderBy('id')->pluck('barangay')->all());
    }

    public function test_comma_separated_user_csv_assignments_produce_row_errors_and_roll_back_accounts(): void
    {
        $this->fixture();
        $before = User::count();
        $row = ['name' => 'CSV area midwife', 'username' => 'csv-valid-area', 'role' => 'midwife',
            'rhu' => 'CSV RHU', 'password' => 'CsvPass123!', 'password_confirmation' => 'CsvPass123!', 'barangay_names' => "San Jose\nPoblacion"];
        $this->upload('users', [$row, array_replace($row, ['username' => 'csv-invalid-area', 'barangay_names' => 'San Jose,Poblacion'])])
            ->assertSessionHasErrors(['file' => 'Row 3 — Enter one barangay per line. Replace commas, semicolons, list separators, numbering, and bullets with line breaks.']);
        $this->assertDatabaseCount('users', $before);
        $this->assertDatabaseCount('midwife_barangay', 0);
        $this->assertStringNotContainsString('CsvPass123!', serialize(session()->all()));
    }

    public function test_import_content_uses_a_compact_dialog_with_a_template_and_no_preparation_panel(): void
    {
        $this->fixture();
        $this->get(route('medicine.index'))->assertOk()->assertSee('<dialog', false)
            ->assertSee('class="csv-import-dialog"', false)->assertSee('Download template')
            ->assertSee('name="import_dialog" value="1"', false)->assertDontSee('Prepare your data');
        $this->get(route('imports.create', ['section' => 'medicines']))->assertOk()
            ->assertSee('Choose a CSV file to import.')->assertSee('Download template')
            ->assertDontSee('Prepare your data')->assertDontSee('csv-import-columns');
        $this->post(route('imports.store', ['section' => 'medicines']), ['import_dialog' => 1,
            'import_token' => (string) Str::uuid()])->assertRedirect(route('imports.create', ['section' => 'medicines']))
            ->assertSessionHasErrors('file');
        $this->get(route('imports.create', ['section' => 'medicines']))->assertOk()->assertSee('Review the import errors');
    }
}
