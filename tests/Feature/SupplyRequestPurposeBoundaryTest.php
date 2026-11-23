<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplyRequestPurposeBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): Medicine
    {
        $unit = HealthUnit::create(['name' => 'Purpose boundary RHU']);
        $user = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $this->withSession(['user_id' => $user->id]);

        return Medicine::create(['medicine_name' => 'Purpose boundary medicine']);
    }

    public function test_each_urgency_accepts_its_full_purpose_without_overflowing_the_stored_destination(): void
    {
        $medicine = $this->signIn();
        foreach (['normal' => '', 'urgent' => '[URGENT] ', 'emergency' => '[EMERGENCY] '] as $urgency => $tag) {
            $purpose = str_repeat('ñ', 255 - strlen($tag));
            $this->post(route('supply_requests.store'), ['medicine_id' => $medicine->id,
                'quantity' => 1, 'purpose' => $purpose, 'urgency' => $urgency])
                ->assertRedirect(route('supply_requests.index'))->assertSessionHasNoErrors();
            $stored = Allocation::latest('id')->firstOrFail();
            $this->assertSame($tag.$purpose, $stored->destination);
            $this->assertSame(255, mb_strlen($stored->destination));
        }
        $this->assertDatabaseCount('allocations', 3);
    }

    public function test_excess_purpose_length_returns_validation_feedback_before_creating_any_request(): void
    {
        $medicine = $this->signIn();
        foreach (['normal' => 255, 'urgent' => 246, 'emergency' => 243] as $urgency => $limit) {
            $purpose = str_repeat('x', $limit + 1);
            $this->from(route('supply_requests.create'))->post(route('supply_requests.store'), [
                'medicine_id' => $medicine->id, 'quantity' => 1, 'purpose' => $purpose, 'urgency' => $urgency,
            ])->assertRedirect(route('supply_requests.create'))->assertSessionHasErrors('purpose')
                ->assertSessionHasInput('purpose', $purpose)->assertSessionHasInput('urgency', $urgency);
            $this->get(route('supply_requests.create'))->assertOk()
                ->assertSee('Maximum '.$limit.' characters for this urgency.');
        }
        $this->assertDatabaseCount('allocations', 0);
    }
}
