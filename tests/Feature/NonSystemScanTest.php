<?php

namespace Tests\Feature;

use App\Models\CheckerAssignment;
use App\Models\CycleWarehouse;
use App\Models\DiscoveredScanTransaction;
use App\Models\ScanSession;
use App\Models\StockOpnameCycle;
use App\Models\StockOpnameDiscoveredItem;
use App\Models\User;
use App\Services\ErpCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class NonSystemScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_barcode_returns_non_system_prompt_without_stock_information(): void
    {
        [$checker, $cycle, $warehouse, $session] = $this->openSession();

        $erp = Mockery::mock(ErpCatalogService::class);
        $erp->shouldReceive('findBarcode')->once()->with($cycle->source_database, 'NOERP001')->andReturn(null);
        $this->app->instance(ErpCatalogService::class, $erp);

        $response = $this->actingAs($checker)->postJson(route('checker.scan.store', $session), [
            'barcode' => 'NOERP001',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('requires_non_system', true)
            ->assertJsonPath('barcode', 'NOERP001')
            ->assertJsonMissing(['system_qty' => 0, 'variance' => 0]);
    }

    public function test_checker_can_store_non_system_scan_with_uom_conversion(): void
    {
        [$checker, $cycle, $warehouse, $session] = $this->openSession();

        $erp = Mockery::mock(ErpCatalogService::class);
        $erp->shouldReceive('findBarcode')->once()->with($cycle->source_database, 'NOERP001')->andReturn(null);
        $this->app->instance(ErpCatalogService::class, $erp);

        $this->actingAs($checker)->postJson(route('checker.scan.non-system', $session), [
            'barcode' => 'NOERP001',
            'item_name' => 'Barang Temuan Karton',
            'qty' => 2,
            'uom_code' => 'KRTN',
            'smallest_uom_code' => 'PCS',
            'ratio_to_smallest' => 20,
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('non_system', true);

        $item = StockOpnameDiscoveredItem::query()->where('cycle_id', $cycle->id)->where('alias_code', 'NOERP001')->firstOrFail();

        $this->assertDatabaseHas('discovered_scan_transactions', [
            'cycle_id' => $cycle->id,
            'warehouse_id' => $warehouse->id,
            'discovered_item_id' => $item->id,
            'checker_id' => $checker->id,
            'input_qty' => '2.0000',
            'input_uom_code' => 'KRTN',
            'smallest_uom_code' => 'PCS',
            'ratio_used' => '20.0000',
            'physical_qty' => '40.0000',
        ]);
    }

    public function test_repeated_discovered_barcode_reuses_item_name_but_allows_new_qty_and_uom(): void
    {
        [$checker, $cycle, $warehouse, $session] = $this->openSession();

        $item = StockOpnameDiscoveredItem::create([
            'cycle_id' => $cycle->id,
            'alias_code' => 'NOERP001',
            'item_name' => 'Barang Temuan',
            'default_uom_code' => 'BOX',
            'smallest_uom_code' => 'PCS',
            'default_ratio_to_smallest' => 10,
            'created_by' => $checker->id,
        ]);

        $erp = Mockery::mock(ErpCatalogService::class);
        $erp->shouldReceive('findBarcode')->twice()->andReturn(null);
        $this->app->instance(ErpCatalogService::class, $erp);

        $this->actingAs($checker)->postJson(route('checker.scan.store', $session), ['barcode' => 'NOERP001'])
            ->assertOk()
            ->assertJsonPath('requires_non_system', true)
            ->assertJsonPath('known_non_system', true)
            ->assertJsonPath('item_name', 'Barang Temuan')
            ->assertJsonPath('uom_code', 'BOX')
            ->assertJsonPath('ratio_to_smallest', '10.0000');

        $this->actingAs($checker)->postJson(route('checker.scan.non-system', $session), [
            'barcode' => 'NOERP001',
            'qty' => 3,
            'uom_code' => 'SET',
            'smallest_uom_code' => 'PCS',
            'ratio_to_smallest' => 5,
        ])->assertOk();

        $this->assertSame(1, StockOpnameDiscoveredItem::whereKey($item->id)->count());
        $this->assertDatabaseHas('discovered_scan_transactions', [
            'discovered_item_id' => $item->id,
            'input_qty' => '3.0000',
            'input_uom_code' => 'SET',
            'ratio_used' => '5.0000',
            'physical_qty' => '15.0000',
        ]);
    }

    private function openSession(): array
    {
        $checker = User::factory()->create(['role' => User::ROLE_CHECKER]);
        $cycle = StockOpnameCycle::factory()->create([
            'status' => StockOpnameCycle::STATUS_OPEN,
            'source_database' => StockOpnameCycle::DB_INGCO,
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

        return [$checker, $cycle, $warehouse, $session];
    }
}
