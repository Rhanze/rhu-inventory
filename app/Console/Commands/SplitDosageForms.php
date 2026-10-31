<?php

namespace App\Console\Commands;

use App\Models\Medicine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SplitDosageForms extends Command
{
    protected $signature = 'medicines:split-dosage
                            {--dry-run : Preview changes without saving}
                            {--force   : Skip confirmation prompt}';

    protected $description = 'Split dosage form out of the dosage column into dosage_form column';

    protected array $forms = [
        // Multi-word first
        'Prefilled Syringe',
        'Pre-filled Syringe',
        'Softgel Capsule',
        'Soft Gel Capsule',
        'Film-coated Tablet',
        'Film Coated Tablet',
        'Sugar-coated Tablet',
        'Chewable Tablet',
        'Effervescent Tablet',
        'Dispersible Tablet',
        'Extended Release Tablet',
        'Sustained Release Tablet',
        'Delayed Release Tablet',
        'Oral Suspension',
        'Oral Solution',
        'Oral Drops',
        'Nasal Spray',
        'Eye Drops',
        'Ear Drops',
        'Eye Ointment',
        'Eye Suspension',
        'Powder for Injection',
        'Powder for Suspension',
        'Powder for Solution',
        'Dry Syrup',
        'Nebulizer Solution',

        // Single-word forms
        'Tablet', 'Tab',
        'Capsule', 'Cap',
        'Suspension', 'Susp',
        'Syrup', 'Syr',
        'Solution', 'Soln',
        'Sachet',
        'Ampoule', 'Ampule', 'Amp',
        'Vial',
        'Injection', 'Inj',
        'Infusion',
        'Cream',
        'Ointment', 'Oint',
        'Gel',
        'Lotion',
        'Drops', 'Drop',
        'Spray',
        'Inhaler',
        'Suppository',
        'Pessary',
        'Patch',
        'Powder',
        'Granules',
        'Lozenge',
        'Device',
        'Test Kit',
        'Strip',
        'Syringe',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rows   = Medicine::query()
            ->whereNotNull('dosage')
            ->where('dosage', '!=', '')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('No rows with a dosage value found.');
            return self::SUCCESS;
        }

        $this->info("Scanning {$rows->count()} rows...\n");

        $changes = [];
        $skipped = [];

        foreach ($rows as $m) {
            $raw = trim($m->dosage);

            if (!empty($m->dosage_form)) {
                continue;
            }

            $parsed = $this->parseDosage($raw);

            if ($parsed === null) {
                $skipped[] = [$m->id, $m->medicine_name, $raw];
                continue;
            }

            [$strength, $form] = $parsed;

            // If strength is null, form-only row → strength stays empty
            if ($strength === null && $form === null) {
                continue;
            }

            $changes[] = [
                'id'       => $m->id,
                'name'     => $m->medicine_name,
                'old'      => $raw,
                'strength' => $strength ?? '',
                'form'     => $form,
            ];
        }

        if (!empty($changes)) {
            $this->table(
                ['ID', 'Medicine', 'Old dosage', '→ New strength', '→ New form'],
                array_map(fn ($c) => [
                    $c['id'], $c['name'], $c['old'],
                    $c['strength'] === '' ? '(empty)' : $c['strength'],
                    $c['form'],
                ], $changes)
            );
        }

        $this->newLine();
        $this->line('Total rows scanned:    ' . $rows->count());
        $this->line('Rows to update:        ' . count($changes));
        $this->line('Rows could not parse:  ' . count($skipped));

        if (!empty($skipped)) {
            $this->newLine();
            $this->warn('Could not auto-detect — will need manual fix:');
            $this->table(['ID', 'Medicine', 'Dosage'], $skipped);
        }

        if (empty($changes)) {
            $this->info("\nNothing to update. Done.");
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('DRY RUN — no changes were saved.');
            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('Apply these changes to the database?', false)) {
            $this->warn('Aborted. Nothing was saved.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as $c) {
                Medicine::where('id', $c['id'])->update([
                    'dosage' => $c['strength'],  
                    'dosage_form' => $c['form'],
                ]);
            }
        });

        $this->newLine();
        $this->info('✓ Successfully updated ' . count($changes) . ' rows.');
        return self::SUCCESS;
    }

    protected function parseDosage(string $raw): ?array
    {
        // Case 1: entire value IS a form (e.g. "Tablet", "Capsule")
        foreach ($this->forms as $form) {
            if (strcasecmp($raw, $form) === 0) {
                return [null, $this->titleCase($form)];
            }
        }

        // Case 2: strength + form at end
        foreach ($this->forms as $form) {
            $pattern = '/^(.*?)\s+' . preg_quote($form, '/') . '\s*(\([^)]*\))?\s*$/i';
            if (preg_match($pattern, $raw, $m)) {
                $strength = trim($m[1]);
                $suffix   = isset($m[2]) ? ' ' . trim($m[2]) : '';
                if ($strength === '') continue;
                return [$strength, $this->titleCase($form) . $suffix];
            }
        }

        return null;
    }

    protected function titleCase(string $s): string
    {
        return implode(' ', array_map(
            fn ($w) => ucfirst(strtolower($w)),
            preg_split('/\s+/', $s)
        ));
    }
}