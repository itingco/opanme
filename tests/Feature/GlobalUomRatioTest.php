<?php

namespace Tests\Feature;

use App\Models\CheckerAssignment;
use App\Models\CycleWarehouse;
use App\Models\ScanSession;
use App\Models\StockOpnameCycle;
use App\Models\UomRatio;
use App\Models\User;
use App\Services\ErpCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GlobalUomRatioTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_global_ratio_by_item_code_and_uom(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)->post('/admin/ratios', [
            'item_code' => 'item-a',
            'uom_code' => 'ktk',
            'ratio' => 20,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('uom_ratios', [
            'item_code' => 'ITEM-A',
            'uom_code' => 'KTK',
            'ratio' => 20,
        ]);
    }

    public function test_scan_uses_same_ratio_for_any_erp_database(): void
    {
        $checker = User::factory()->create(['role' => User::ROLE_CHECKER]);
        $cycle = StockOpnameCycle::factory()->create([
            'status' => StockOpnameCycle::STATUS_OPEN,
            'source_database' => 'AS_SMI',
        ]);
        $warehouse = CycleWarehouse::factory()->create(['cycle_id' => $cycle->id]);

        CheckerAssignment::create([
            'cycle_id' => $cycle->id,
            'warehouse_id' => $warehouse->id,
            'checker_id' => $checker->id,
            'assigned_by' => $checker->id,
        ]);

        $session = ScanSession::create([
            'cycle_id' => $cycle->id,
            'warehouse_id' => $warehouse->id,
            'checker_id' => $checker->id,
            'location' => 'RAK A1',
            'started_at' => now(),
        ]);

        UomRatio::create([
            'item_code' => 'ITEM-A',
            'uom_code' => 'KTK',
            'ratio' => 20,
        ]);

        $erp = Mockery::mock(ErpCatalogService::class);
        $erp->shouldReceive('findBarcode')
            ->once()
            ->with('AS_SMI', '8990001')
            ->andReturn([
                'alias_code' => '8990001',
                'item_id' => 100,
                'item_code' => 'ITEM-A',
                'item_name' => 'Item A',
                'uom_level' => 2,
                'uom_code' => 'KTK',
            ]);
        $this->app->instance(ErpCatalogService::class, $erp);

        $response = $this->actingAs($checker)->postJson("/checker/sessions/{$session->id}/scan", [
            'barcode' => '8990001',
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertDatabaseHas('scan_transactions', [
            'cycle_id' => $cycle->id,
            'item_code' => 'ITEM-A',
            'uom_code' => 'KTK',
            'ratio_used' => 20,
            'physical_qty' => 20,
        ]);
    }
}
