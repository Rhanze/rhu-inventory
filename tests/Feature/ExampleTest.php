<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_root_renders_public_medicine_search(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertViewIs('public.home')
            ->assertSee('action="'.route('public.home.citizen').'"', false)
            ->assertSee('name="q"', false)
            ->assertSee('name="health_unit_id"', false)
            ->assertDontSee('name="password"', false);
    }
}
