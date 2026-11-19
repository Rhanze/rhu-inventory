<?php

namespace App\Services;

use App\Helpers\ActivityLogger;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\PublicUser;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class CsvImportService
{
    public const MAX_ROWS = 500;

    private const INFORMATIONAL_COLUMNS = [
        'id', 'added_by', 'allocated_by', 'dispensed_by', 'received_by', 'requested_by',
        'status', 'has_prescription', 'facility_type', 'items_count', 'total_units',
        'rhus_w_stock', 'available', 'allocated', 'created_at', 'updated_at', 'deleted_at', 'recipient',
        'row_number', 'prescription', 'dispensed',
    ];

    public function canImport(string $section): bool
    {
        $definition = config('csv_imports')[$section] ?? null;

        return $definition && session('user_id')
            && in_array(session('role'), $definition['roles'], true)
            && ($definition['scope'] !== 'rhu' || session('health_unit_id'))
            && ($definition['scope'] !== 'pho' || is_null(session('health_unit_id')));
    }

    public function definition(string $section): array
    {
        abort_unless(array_key_exists($section, config('csv_imports')), 404);
        abort_unless($this->canImport($section), 403);

        return config('csv_imports')[$section];
    }

    public function import(Request $request, string $section): int
    {
        $definition = $this->definition($section);
        $records = $this->read($request->file('file'), $section, $definition);
        $hash = hash_file('sha256', $request->file('file')->getRealPath());
        $token = $request->string('import_token')->toString();
        $originalSession = $request->session()->all();

        try {
            return DB::transaction(function () use ($request, $section, $definition, $records, $hash, $token) {
                // Serializing one operator's imports also makes a retried POST safe.
                User::whereKey(session('user_id'))->lockForUpdate()->firstOrFail();
                $previous = DB::table('csv_import_batches')->where('token', $token)->first();
                if ($previous) {
                    if ((int) $previous->user_id !== (int) session('user_id')
                        || $previous->section !== $section || $previous->file_hash !== $hash) {
                        throw ValidationException::withMessages(['file' => 'This import form was already used for a different file. Open a new import form.']);
                    }

                    return (int) $previous->row_count;
                }

                $entries = $section === 'shipments' ? $this->shipmentEntries($records) : $records;
                $errors = [];
                foreach ($entries as $entry) {
                    if (! DB::connection()->getPdo()->inTransaction()) {
                        throw ValidationException::withMessages(['file' => 'The import transaction was interrupted. Upload the file again.']);
                    }
                    try {
                        DB::transaction(function () use ($request, $section, $definition, $entry) {
                            $data = $this->prepare($entry['data'], $section, $definition);
                            $this->storeRow($request, $definition['store'], $data);
                            if (! DB::connection()->getPdo()->inTransaction()) {
                                throw new \RuntimeException('The CSV import transaction ended unexpectedly.');
                            }
                        });
                    } catch (ValidationException $error) {
                        foreach ($error->errors() as $field => $messages) {
                            $row = $entry['row'];
                            if (preg_match('/^items\.(\d+)\./', $field, $match)) {
                                $row = $entry['item_rows'][(int) $match[1]] ?? $row;
                            }
                            foreach ($messages as $message) {
                                $errors[] = 'Row '.$row.' — '.$message;
                            }
                        }
                    } catch (ModelNotFoundException|HttpExceptionInterface $error) {
                        $errors[] = 'Row '.$entry['row'].' — A referenced record is unavailable or outside your access.';
                    } catch (\Throwable $error) {
                        report($error);
                        throw ValidationException::withMessages(['file' => 'Row '.$entry['row'].' could not be saved. Check its values and try again.']);
                    }

                    if (count($errors) >= 20) {
                        $errors = array_slice($errors, 0, 20);
                        $errors[] = 'Showing the first 20 errors. Correct these rows and upload the file again.';
                        break;
                    }
                }

                if ($errors) {
                    throw ValidationException::withMessages(['file' => $errors]);
                }

                DB::table('csv_import_batches')->insert([
                    'token' => $token, 'user_id' => session('user_id'), 'section' => $section,
                    'file_hash' => $hash, 'row_count' => count($records), 'imported_at' => now(),
                ]);
                ActivityLogger::log('CSV Import', 'Create', 'Imported '.count($records).' CSV rows into '.$definition['title'].'.');

                return count($records);
            });
        } finally {
            // Store actions flash row input. Keep patient data and passwords out of the import session.
            $request->session()->replace($originalSession);
        }
    }

    private function read(UploadedFile $file, string $section, array $definition): array
    {
        $contents = file_get_contents($file->getRealPath());
        if (str_starts_with($contents, "\xFF\xFE") || str_starts_with($contents, "\xFE\xFF")) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'UTF-16');
        }
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        if (! mb_check_encoding($contents, 'UTF-8') || str_contains($contents, "\0")) {
            throw ValidationException::withMessages(['file' => 'Save the file as UTF-8 CSV and upload it again.']);
        }

        $firstLine = strtok($contents, "\r\n");
        $delimiter = ',';
        $columnCount = 0;
        foreach ([',', ';', "\t"] as $candidate) {
            $count = count(str_getcsv($firstLine ?: '', $candidate, '"', ''));
            if ($count > $columnCount) {
                $delimiter = $candidate;
                $columnCount = $count;
            }
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        try {
            $headers = fgetcsv($stream, 0, $delimiter, '"', '');
            if (! $headers) {
                throw ValidationException::withMessages(['file' => 'The CSV file is empty. Download the template to see its columns.']);
            }
            $aliases = array_merge([
                'batch_number' => 'batch_no', 'expiry_date' => 'expiration_date',
                'medicine_name' => 'medicine', 'item' => 'medicine', 'rhu_name' => 'rhu',
            ], $definition['aliases'] ?? []);
            if ($section === 'medicines') {
                $aliases['medicine_name'] = 'medicine_name';
            }
            $headers = array_map(function ($header) use ($aliases) {
                if (trim($header) === '#') {
                    return 'row_number';
                }
                $header = trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim($header))), '_');

                return $aliases[$header] ?? $header;
            }, $headers);
            if (in_array('', $headers, true) || count($headers) !== count(array_unique($headers))) {
                throw ValidationException::withMessages(['file' => 'CSV column names must be present and unique.']);
            }

            $allowed = array_merge($definition['columns'], self::INFORMATIONAL_COLUMNS,
                ['rhu', 'health_unit_id', 'dosage', 'dosage_form', 'category', 'packaging', 'type', 'movement_type', 'batch_no', 'expiration_date']);
            $unknown = array_diff($headers, $allowed);
            if ($unknown) {
                throw ValidationException::withMessages(['file' => 'Unrecognized columns. Use the template column names.']);
            }

            $records = [];
            $row = 1;
            while (($values = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
                $row++;
                if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                if (count($values) !== count($headers)) {
                    throw ValidationException::withMessages(['file' => 'Row '.$row.' has a different number of columns from the header. Quote values containing commas.']);
                }
                $data = array_combine($headers, array_map(fn ($value) => trim((string) $value) === '' ? null : trim($value), $values));
                $records[] = ['row' => $row, 'data' => $data];
                if (count($records) > self::MAX_ROWS) {
                    throw ValidationException::withMessages(['file' => 'Import up to '.self::MAX_ROWS.' rows per file. Split this file into smaller CSV files.']);
                }
            }
            if (! $records) {
                throw ValidationException::withMessages(['file' => 'Add at least one data row below the CSV header.']);
            }

            return $records;
        } finally {
            fclose($stream);
        }
    }

    private function prepare(array $data, string $section, array $definition): array
    {
        if ($definition['scope'] === 'rhu') {
            if (! empty($data['health_unit_id']) && (int) $data['health_unit_id'] !== (int) session('health_unit_id')) {
                throw ValidationException::withMessages(['rhu' => 'The RHU must match your current RHU.']);
            }
            if (! empty($data['rhu'])) {
                $rhu = $this->healthUnitId($data['rhu'], (int) session('health_unit_id'));
                if ($rhu !== (int) session('health_unit_id')) {
                    throw ValidationException::withMessages(['rhu' => 'The RHU must match your current RHU.']);
                }
            }
        }

        foreach (['requires_prescription', 'is_pregnant'] as $field) {
            if (isset($data[$field])) {
                $boolean = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($boolean === null) {
                    throw ValidationException::withMessages([$field => $field.' must be Yes/No, true/false, or 1/0.']);
                }
                $data[$field] = $boolean ? 1 : 0;
            }
        }

        if (in_array('medicine', $definition['columns'], true) && ! empty($data['medicine'])) {
            $matches = Medicine::where('medicine_name', $data['medicine'])
                ->when(! empty($data['medicine_id']), fn ($q) => $q->whereKey($data['medicine_id']))
                ->when(! empty($data['dosage']), fn ($q) => $q->where('dosage', $data['dosage']))
                ->when(! empty($data['dosage_form']), fn ($q) => $q->where('dosage_form', $data['dosage_form']))
                ->when(! empty($data['category']), fn ($q) => $q->where('category', $data['category']))
                ->limit(2)->pluck('id');
            if ($matches->count() !== 1 || (! empty($data['medicine_id']) && (int) $data['medicine_id'] !== (int) $matches->first())) {
                throw ValidationException::withMessages(['medicine' => 'Medicine name must identify one existing item. Use medicine_id to distinguish duplicate names.']);
            }
            $data['medicine_id'] = $matches->first();
        }

        $locations = ['source_rhu' => $section === 'distribution' ? 'from_health_unit_id' : 'source_rhu_id',
            'destination_rhu' => $section === 'distribution' ? 'to_health_unit_id' : 'destination_health_unit_id'];
        foreach ($locations as $name => $field) {
            if (! empty($data[$name])) {
                $id = $this->healthUnitId(preg_replace('/\s+\(RHU\)$/i', '', $data[$name]), ! empty($data[$field]) ? (int) $data[$field] : null);
                if (! empty($data[$field]) && (int) $data[$field] !== $id) {
                    throw ValidationException::withMessages([$field => 'The RHU name and ID must refer to the same RHU.']);
                }
                $data[$field] = $id;
            }
        }

        if (in_array($section, ['prenatal', 'postpartum', 'family_planning', 'immunization'], true) && ! empty($data['patient'])) {
            $field = $section === 'immunization' ? 'public_user_id' : 'patient_id';
            $matches = PublicUser::forViewer()->where('health_unit_id', session('health_unit_id'))
                ->when(! empty($data[$field]), fn ($q) => $q->whereKey($data[$field]))
                ->get(['id', 'first_name', 'last_name'])->filter(fn ($patient) => mb_strtolower($patient->full_name) === mb_strtolower($data['patient']));
            if ($matches->count() !== 1 || (! empty($data[$field]) && (int) $data[$field] !== (int) $matches->first()->id)) {
                throw ValidationException::withMessages([$field => 'Patient name must identify one patient within your access. Use the patient ID when names repeat.']);
            }
            $data[$field] = $matches->first()->id;
        }

        if (in_array($section, ['allocations', 'dispensing'], true) && empty($data['inventory_id']) && ! empty($data['medicine_id'])) {
            if ($section === 'dispensing' && ($data['stock_source'] ?? 'rhu') === 'allocation') {
                $data['inventory_id'] = app(AllocationDispensingService::class)
                    ->inventories((int) session('health_unit_id'), (int) session('user_id'))
                    ->firstWhere('medicine_id', (int) $data['medicine_id'])?->id;
            } else {
                $data['inventory_id'] = Inventory::availableFefo((int) session('health_unit_id'), (int) $data['medicine_id'])->value('id');
            }
            if (! $data['inventory_id']) {
                throw ValidationException::withMessages(['medicine' => 'No unexpired available inventory for this medicine exists in your RHU.']);
            }
        }
        if ($section === 'allocations' && ! empty($data['recipient_username'])) {
            $matches = User::where('health_unit_id', session('health_unit_id'))->where('role', 'midwife')->where('is_active', true)
                ->when(! empty($data['recipient_user_id']), fn ($q) => $q->whereKey($data['recipient_user_id']))
                ->where(fn ($q) => $q->where('username', $data['recipient_username'])->orWhere('name', $data['recipient_username']))->limit(2)->pluck('id');
            if ($matches->count() !== 1 || (! empty($data['recipient_user_id']) && (int) $data['recipient_user_id'] !== (int) $matches->first())) {
                throw ValidationException::withMessages(['recipient_username' => 'Recipient must identify one active midwife in your RHU. Use recipient_user_id when names repeat.']);
            }
            $data['recipient_user_id'] = $matches->first();
        }
        if ($section === 'stock_logs') {
            $type = preg_replace('/[^a-z]+/', '', mb_strtolower($data['type'] ?? $data['movement_type'] ?? 'addition'));
            if (! in_array($type, ['addition', 'added', 'add', 'stockin'], true)
                || preg_match('/^(released|dispensed|allocated)\b/i', $data['remarks'] ?? '')) {
                throw ValidationException::withMessages(['type' => 'This row is a stock release. Import it through its allocation, dispensing, or transfer section.']);
            }
        }
        if ($section === 'dispensing' && ($data['barangay'] ?? null) === 'Not recorded') {
            $data['barangay'] = null;
        }
        foreach (['quantity_added', 'quantity_dispensed', 'quantity_allocated', 'quantity'] as $field) {
            if (isset($data[$field]) && preg_match('/^(\d+)\s+(.+)$/', $data[$field], $match)) {
                $unit = isset($data['medicine_id']) ? Medicine::find($data['medicine_id'])?->unit : null;
                if ($unit && in_array(mb_strtolower($match[2]), [mb_strtolower($unit), mb_strtolower($unit).'s'], true)) {
                    $data[$field] = $match[1];
                }
            }
        }
        if ($section === 'users') {
            if (! empty($data['rhu'])) {
                $id = $this->healthUnitId($data['rhu'], ! empty($data['health_unit_id']) ? (int) $data['health_unit_id'] : (session('health_unit_id') ? (int) session('health_unit_id') : null));
                if (! empty($data['health_unit_id']) && (int) $data['health_unit_id'] !== $id) {
                    throw ValidationException::withMessages(['rhu' => 'The RHU name and ID must refer to the same RHU.']);
                }
                $data['health_unit_id'] = $id;
            }
            $data['health_unit_id'] = $data['health_unit_id'] ?? session('health_unit_id');
        }
        if (in_array($section, ['inventory', 'stock_logs'], true) && ! empty($data['packaging'])) {
            $packaging = $data['packaging'];
            if (preg_match('/^(\d+)\s+(?:per|in)\s+(.+)$/i', $packaging, $match)
                || preg_match('/^(\d+)\s+[^\/]+\/\s*(.+)$/', $packaging, $match)) {
                $data['pieces_per_pack'] = $data['pieces_per_pack'] ?? $match[1];
                $data['pack_type'] = $data['pack_type'] ?? trim($match[2]);
            } elseif (str_contains($packaging, '/')) {
                $data['pack_type'] = $data['pack_type'] ?? trim(explode('/', $packaging, 2)[1]);
            } else {
                $unit = isset($data['medicine_id']) ? Medicine::find($data['medicine_id'])?->unit : null;
                if (mb_strtolower($packaging) !== mb_strtolower($unit ?? '')) {
                    $data['pack_type'] = $data['pack_type'] ?? $packaging;
                }
            }
        }

        if ($section === 'shipments') {
            foreach ($data['items'] as $index => &$item) {
                try {
                    $item = $this->prepare($item, 'shipment_item', ['scope' => 'any', 'columns' => ['medicine', 'medicine_id', 'quantity', 'batch_no', 'expiration_date', 'storage_location']]);
                } catch (ValidationException $error) {
                    $messages = [];
                    foreach ($error->errors() as $field => $errors) {
                        $messages['items.'.$index.'.'.$field] = $errors;
                    }
                    throw ValidationException::withMessages($messages);
                }
            }
            unset($item);
            $data = array_intersect_key($data, array_flip(['shipment_no', 'reference_no', 'supplier', 'received_date', 'remarks', 'items']));
        } else {
            $data = array_intersect_key($data, array_flip($definition['columns']));
        }

        return $data;
    }

    private function healthUnitId(string $name, ?int $id = null): int
    {
        $matches = HealthUnit::rhuOnly()->where('name', $name)->when($id !== null, fn ($q) => $q->whereKey($id))->limit(2)->pluck('id');
        if ($matches->count() !== 1) {
            throw ValidationException::withMessages(['rhu' => 'RHU name must identify one existing Rural Health Unit. Use its ID when names repeat.']);
        }

        return (int) $matches->first();
    }

    private function shipmentEntries(array $records): array
    {
        $entries = [];
        foreach ($records as $record) {
            $header = array_intersect_key($record['data'], array_flip(['shipment_no', 'reference_no', 'supplier', 'received_date', 'remarks']));
            $key = hash('sha256', json_encode($header));
            if (! isset($entries[$key])) {
                $entries[$key] = ['row' => $record['row'], 'data' => $header + ['items' => []], 'item_rows' => []];
            }
            $entries[$key]['data']['items'][] = $record['data'];
            $entries[$key]['item_rows'][] = $record['row'];
        }

        return array_values($entries);
    }

    private function storeRow(Request $original, string $routeName, array $data): void
    {
        $router = app('router');
        $route = clone $router->getRoutes()->getByName($routeName);
        $route->setContainer(app());
        $request = Request::create(route($routeName), 'POST', $data,
            $original->cookies->all(), [], $original->server->all());
        $request->setLaravelSession($original->session());
        $request->setUserResolver($original->getUserResolver());
        $request->setRouteResolver(fn () => $route);
        $request->headers->replace($original->headers->all());
        $route->bind($request);
        $session = $original->session();
        $saved = $session->all();
        $session->forget(['success', 'error', 'errors', '_old_input']);
        $middleware = array_diff($router->gatherRouteMiddleware($route), $router->gatherRouteMiddleware($original->route()));

        try {
            app()->instance('request', $request);
            $response = (new Pipeline(app()))->send($request)->through($middleware)->then(fn () => $route->run());
            $messages = $session->get('errors')?->getBag('default')->all() ?? [];
            if ($session->has('error')) {
                $messages[] = $session->get('error');
            }
            if ($messages) {
                throw ValidationException::withMessages(['row' => $messages]);
            }
            if (! $session->has('success') || $response->getStatusCode() >= 400) {
                throw ValidationException::withMessages(['row' => 'Your account cannot create this record through its normal workflow.']);
            }
        } finally {
            $session->replace($saved);
            app()->instance('request', $original);
        }
    }
}
