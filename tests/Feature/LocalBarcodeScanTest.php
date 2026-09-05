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

class LocalBarcodeScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_uses_local_barcode_then_resolves_itemcode_uom_to_erp(): void
    {
        [$checker, $cycle, $session] = $this->openSession('AS_SMI');

        ItemBarcode::create([
            'item_code' => 'ITEM-A',
            'barcode' => '8990001',
            'uom_code' => 'KTK',
        ]);
        UomRatio::create([
            'item_code' => 'ITEM-A',
            'uom_code' => 'KTK',
            'ratio' => 20,
        ]);

        $erp = Mockery::mock(ErpCatalogService::class);
        $erp->shouldReceive('findItemByCodeAndUom')
            ->once()
            ->with('AS_SMI', 'ITEM-A', 'KTK')
            ->andReturn([
                'item_id' => 100,
                'item_code' => 'ITEM-A',
                'item_name' => 'Item A',
                'uom_level' => 2,
                'uom_code' => 'KTK',
            ]);
        $erp->shouldNotReceive('findBarcode');
        $this->app->instance(ErpCatalogService::class, $erp);

        $this->actingAs($checker)
            ->postJson("/checker/sessions/{$session->id}/scan", ['barcode' => '8990001'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('scan_transactions', [
            'cycle_id' => $cycle->id,
            'alias_code' => '8990001',
            'item_id' => 100,
            'item_code' => 'ITEM-A',
            'uom_code' => 'KTK',
            'ratio_used' => 20,
            'physical_qty' => 20,
        ]);
    }

    public function test_unknown_local_barcode_enters_non_system_flow_without_erp_barcode_lookup(): void
    {
        [$checker, , $session] = $this->openSession('AS_INGCO');

        $erp = Mockery::mock(ErpCatalogService::class);
        $erp->shouldNotReceive('findBarcode');
        $erp->shouldNotReceive('findItemByCodeAndUom');
        $this->app->instance(ErpCatalogService::class, $erp);

        $this->actingAs($checker)
            ->postJson("/checker/sessions/{$session->id}/scan", ['barcode' => 'NOT-LOCAL'])
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('requires_non_system', true)
            ->assertJsonPath('barcode', 'NOT-LOCAL');
    }

    private function openSession(string $database): array
    {
        $checker = User::factory()->create(['role' => User::ROLE_CHECKER]);
        $cycle = StockOpnameCycle::factory()->create([
            'status' => StockOpnameCycle::STATUS_OPEN,
            'source_database' => $database,
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

        return [$checker, $cycle, $session];
    }
}
