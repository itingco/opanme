<?php

namespace Tests\Feature;

use App\Models\CheckerAssignment;
use App\Models\CycleWarehouse;
use App\Models\ItemBarcode;
use App\Models\ScanSession;
use App\Models\StockOpnameCycle;
use App\Models\UomRatio;
use App\Models\User;
use App\Services\ErpCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ScanPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_scan_response_does_not_expose_ratio_stock_or_variance(): void
    {
        $checker = User::factory()->create(['role' => User::ROLE_CHECKER]);
        $cycle = StockOpnameCycle::factory()->create(['status' => StockOpnameCycle::STATUS_OPEN, 'source_database' => 'AS_INGCO']);
        $warehouse = CycleWarehouse::factory()->create(['cycle_id' => $cycle->id]);
        CheckerAssignment::create(['cycle_id' => $cycle->id, 'warehouse_id' => $warehouse->id, 'checker_id' => $checker->id, 'assigned_by' => $checker->id]);
        $session = ScanSession::create(['cycle_id' => $cycle->id, 'warehouse_id' => $warehouse->id, 'checker_id' => $checker->id, 'location' => 'RAK A1', 'started_at' => now()]);
        ItemBarcode::create(['item_code' => 'ITEM-A', 'barcode' => '8990001', 'uom_code' => 'BOX']);
        UomRatio::create(['item_code' => 'ITEM-A', 'uom_code' => 'BOX', 'ratio' => 20]);

        $erp = Mockery::mock(ErpCatalogService::class);
        $erp->shouldReceive('findItemByCodeAndUom')->once()->with('AS_INGCO', 'ITEM-A', 'BOX')->andReturn([
            'item_id' => 100, 'item_code' => 'ITEM-A', 'item_name' => 'Item A', 'uom_level' => 2, 'uom_code' => 'BOX',
        ]);
        $this->app->instance(ErpCatalogService::class, $erp);

        $response = $this->actingAs($checker)->postJson("/checker/sessions/{$session->id}/scan", ['barcode' => '8990001']);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonMissing(['ratio' => 20])
            ->assertJsonMissing(['physical_qty' => 20])
            ->assertJsonMissing(['variance' => 0])
            ->assertJsonMissing(['system_qty' => 0]);
    }
}
