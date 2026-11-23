<?php

namespace Tests\Feature;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FormChoiceSearchTest extends TestCase
{
    public function test_search_fields_keep_native_values_for_form_choices_filters_and_destinations(): void
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null) {
            $this->markTestSkipped('Node.js is required for dropdown interaction checks.');
        }

        $process = new Process([$node, base_path('tests/Support/form-choice-search.cjs'), public_path('js/form-choice-search.js')]);
        $process->mustRun();
        $this->assertStringContainsString('Search choices passed', $process->getOutput());
    }
}
