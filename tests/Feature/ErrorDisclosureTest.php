<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorDisclosureTest extends TestCase
{
    use RefreshDatabase;

    private function stockFixture(): Inventory
    {
        $unit = HealthUnit::create(['name' => 'Error handling RHU']);
        $user = User::factory()->create(['role' => 'admin', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Error handling medicine', 'requires_prescription' => false]);
        $inventory = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'available_stock' => 10, 'allocated_stock' => 0, 'expiration_date' => today()->addMonth()]);
        $this->withSession(['user_id' => $user->id, 'role' => 'admin', 'health_unit_id' => $unit->id]);

        return $inventory;
    }

    public function test_internal_transaction_errors_are_hidden_from_allocation_and_dispensing_responses(): void
    {
        $inventory = $this->stockFixture();
        $databaseManager = \Mockery::mock(DB::getFacadeRoot());
        $databaseManager->shouldReceive('transaction')->twice()
            ->andThrow(new \RuntimeException('SQLSTATE private_database_secret /private/config'));
        DB::swap($databaseManager);

        $this->post(route('allocation.store'), ['inventory_id' => $inventory->id, 'destination' => 'Clinic',
            'quantity_allocated' => 1, 'date_allocated' => today()->toDateString()])
            ->assertRedirect()->assertSessionHas('error', 'Unable to save the allocation. Please try again.');
        $this->post(route('dispensing.store'), ['inventory_id' => $inventory->id,
            'quantity_dispensed' => 1, 'date_dispensed' => today()->toDateString()])
            ->assertRedirect()->assertSessionHas('error', 'Unable to save the dispensing record. Please try again.');

        $this->assertStringNotContainsString('private_database_secret', serialize(session()->all()));
        $this->assertDatabaseCount('allocations', 0);
        $this->assertDatabaseCount('dispensing_records', 0);
        $this->assertSame(10, $inventory->fresh()->available_stock);
    }

    public function test_allocation_stock_validation_keeps_its_actionable_message(): void
    {
        $inventory = $this->stockFixture();
        $this->post(route('allocation.store'), ['inventory_id' => $inventory->id, 'destination' => 'Clinic',
            'quantity_allocated' => 20, 'date_allocated' => today()->toDateString()])
            ->assertRedirect()->assertSessionHas('error', 'Insufficient stock. Available across all unexpired batches: 10 unit(s). Requested: 20.');
        $this->assertSame(10, $inventory->fresh()->available_stock);
    }

    public function test_dispensing_stock_validation_keeps_its_actionable_message(): void
    {
        $inventory = $this->stockFixture();
        $this->post(route('dispensing.store'), ['inventory_id' => $inventory->id,
            'quantity_dispensed' => 20, 'date_dispensed' => today()->toDateString()])
            ->assertRedirect()->assertSessionHas('error', 'Insufficient stock for the dispensing date. Eligible stock: 10 unit(s).');
        $this->assertSame(10, $inventory->fresh()->available_stock);
        $this->assertDatabaseCount('dispensing_records', 0);
    }

    public function test_production_errors_hide_source_even_when_app_debug_is_enabled(): void
    {
        $previousEnvironment = $_ENV;
        $previousServer = $_SERVER;

        try {
            $_ENV['APP_DEBUG'] = $_SERVER['APP_DEBUG'] = 'true';
            $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'local';
            $this->assertTrue((require config_path('app.php'))['debug']);

            $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';
            $configuration = require config_path('app.php');
            $this->assertFalse($configuration['debug']);
            config(['app.debug' => $configuration['debug']]);
            Route::get('/security-error-test', fn () => throw new \RuntimeException('private_source_marker'));

            $this->get('/security-error-test')->assertStatus(500)
                ->assertDontSee('private_source_marker')->assertDontSee('Stack trace');
        } finally {
            $_ENV = $previousEnvironment;
            $_SERVER = $previousServer;
        }
    }
}
