<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockLogAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_logs_and_the_filtered_history_link_render_with_missing_public_assets(): void
    {
        $unit = HealthUnit::create(['name' => 'Asset test RHU']);
        $medicine = Medicine::create(['medicine_name' => 'Asset test medicine']);
        $batch = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'batch_no' => 'ASSET-LOT', 'available_stock' => 5]);
        $log = StockLog::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'inventory_id' => $batch->id, 'batch_no' => $batch->batch_no,
            'quantity_added' => 5, 'date_added' => today(), 'movement_type' => 'receipt']);

        $public = public_path();
        $isolatedPublic = storage_path('framework/testing-public-'.Str::uuid());
        File::ensureDirectoryExists($isolatedPublic.'/css');
        File::ensureDirectoryExists($isolatedPublic.'/js');
        $this->withoutVite();
        $sharedAssets = ['css/page-headings.css', 'js/page-headings.js', 'css/form-choice-search.css', 'js/form-choice-search.js'];

        try {
            foreach ($sharedAssets as $asset) {
                File::put($isolatedPublic.'/'.$asset, '/* Fixture asset */');
            }
            $this->app->usePublicPath($isolatedPublic);

            foreach ([['admin', $unit->id], ['staff', $unit->id], ['admin', null]] as [$role, $unitId]) {
                $viewer = User::factory()->create(['role' => $role, 'health_unit_id' => $unitId]);
                $this->withSession(['user_id' => $viewer->id])->get(route('stock_logs.index'))
                    ->assertOk()->assertSee($batch->batch_no)->assertDontSee('stock-movements.css');
            }

            $viewer = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
            $otherBatch = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'batch_no' => 'OTHER-LOT', 'available_stock' => 3]);
            $otherLog = StockLog::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'inventory_id' => $otherBatch->id, 'batch_no' => $otherBatch->batch_no,
                'quantity_added' => 3, 'date_added' => today(), 'movement_type' => 'receipt']);
            $batchHistoryUrl = route('stock_logs.index', ['inventory_id' => $batch->id, 'status' => 'all']);
            $this->withSession(['user_id' => $viewer->id])->get(route('inventory.index'))->assertOk()
                ->assertSee('href="'.e($batchHistoryUrl).'" title="Movement history"', false);
            $this->get($batchHistoryUrl)->assertOk()
                ->assertSee('id="stock-log-'.$log->id.'"', false)
                ->assertDontSee('id="stock-log-'.$otherLog->id.'"', false);

            $history = $this->withSession(['user_id' => $viewer->id])->get(route('inventory.index', [
                'view' => 'history', 'medicine_id' => $medicine->id, 'batch_no' => $batch->batch_no,
            ]))->assertOk();
            preg_match('/href="([^"]+)">View these movements in Stock Logs/', $history->getContent(), $link);
            $this->assertNotEmpty($link);
            $stockLogsUrl = html_entity_decode($link[1]);
            $this->get($stockLogsUrl)->assertOk()->assertSee('stock-log-'.$log->id, false)
                ->assertDontSee('stock-movements.css');

            foreach ($sharedAssets as $asset) {
                File::delete($isolatedPublic.'/'.$asset);
            }
            $this->get($stockLogsUrl)->assertOk()->assertSee($batch->batch_no)->assertDontSee('page-headings.css');

            $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
            $this->withSession(['user_id' => $midwife->id])->get($stockLogsUrl)->assertRedirect(route('dashboard.midwife'));
        } finally {
            $this->app->usePublicPath($public);
            File::deleteDirectory($isolatedPublic);
        }
    }
}
