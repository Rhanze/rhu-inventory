<?php

namespace App\Http\Controllers;

use App\Services\CsvImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CsvImportController extends Controller
{
    public function index(CsvImportService $importer)
    {
        $sections = array_filter(config('csv_imports'), fn ($definition, $key) => $importer->canImport($key), ARRAY_FILTER_USE_BOTH);

        return view('imports.index', compact('sections'));
    }

    public function create(string $section, CsvImportService $importer)
    {
        $definition = $importer->definition($section);
        $sections = array_filter(config('csv_imports'), fn ($definition, $key) => $importer->canImport($key), ARRAY_FILTER_USE_BOTH);
        $token = old('import_token', (string) Str::uuid());

        return view('imports.create', compact('section', 'definition', 'sections', 'token'));
    }

    public function template(string $section, CsvImportService $importer)
    {
        $definition = $importer->definition($section);

        return response()->streamDownload(function () use ($definition) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $definition['columns'], ',', '"', '');
            fclose($stream);
        }, $section.'-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }

    public function store(Request $request, string $section, CsvImportService $importer)
    {
        $definition = $importer->definition($section);
        try {
            $request->validate([
                'file' => ['required', 'file', 'max:4096', 'extensions:csv,txt'],
                'import_token' => ['required', 'uuid'],
            ]);
            $count = $importer->import($request, $section);
        } catch (ValidationException $error) {
            if ($request->boolean('import_dialog') && ! $request->expectsJson()) {
                return redirect()->route('imports.create', ['section' => $section])
                    ->withInput($request->only('import_token'))->withErrors($error->errors());
            }
            throw $error;
        } catch (\Throwable $error) {
            report($error);

            return redirect()->route('imports.create', ['section' => $section])->withInput($request->only('import_token'))->withErrors([
                'file' => 'The import could not be saved. Please try again.',
            ]);
        }

        return redirect()->route($definition['index'])->with('success',
            $count.' CSV '.Str::plural('row', $count).' imported into '.$definition['title'].'.');
    }
}
