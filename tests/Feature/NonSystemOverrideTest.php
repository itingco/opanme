<?php

namespace Tests\Feature;

use App\Models\CycleWarehouse;
use App\Models\StockOpnameCycle;
use App\Models\StockOpnameDiscoveredItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NonSystemOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_non_system_item_by_manual_override_while_closed(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $cycle = StockOpnameCycle::factory()->create(['status' => StockOpnameCycle::STATUS_CLOSED, 'completed_at' => now()]);
        $warehouse = CycleWarehouse::factory()->create(['cycle_id' => $cycle->id]);

        $this->actingAs($admin)->post(route('admin.cycles.summary.non-system.store', $cycle), [
            'warehouse_id' => $warehouse->id,
            'barcode' => 'MANUAL-001',
            'item_name' => 'Barang temuan manual',
            'qty' => 2,
            'uom_code' => 'BOX',
            'smallest_uom_code' => 'PCS',
            'ratio_to_smallest' => 12,
            'comment' => 'Ditemukan saat recount admin.',
        ])->assertRedirect();

        $item = StockOpnameDiscoveredItem::where('cycle_id', $cycle->id)->where('alias_code', 'MANUAL-001')->firstOrFail();
        $this->assertDatabaseHas('stock_opname_discovered_overrides', [
            'cycle_id' => $cycle->id,
            'warehouse_id' => $warehouse->id,
            'discovered_item_id' => $item->id,
            'input_qty' => '2.0000',
            'ratio_used' => '12.0000',
            'override_qty' => '24.0000',
            'updated_by' => $admin->id,
        ]);
    }

    public function test_finalized_cycle_rejects_non_system_override_changes(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $cycle = StockOpnameCycle::factory()->create(['status' => StockOpnameCycle::STATUS_FINALIZED, 'finalized_at' => now(), 'finalized_by' => $admin->id]);
        $warehouse = CycleWarehouse::factory()->create(['cycle_id' => $cycle->id]);
        $item = StockOpnameDiscoveredItem::create([
            'cycle_id' => $cycle->id,
            'alias_code' => 'MANUAL-001',
            'item_name' => 'Barang temuan manual',
            'default_uom_code' => 'PCS',
            'smallest_uom_code' => 'PCS',
            'default_ratio_to_smallest' => 1,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->put(route('admin.cycles.summary.non-system.override', [$cycle, $warehouse->id, $item->id]), [
            'qty' => 3,
            'uom_code' => 'PCS',
            'smallest_uom_code' => 'PCS',
            'ratio_to_smallest' => 1,
            'comment' => 'Tidak boleh setelah final.',
        ])->assertSessionHasErrors('override');

        $this->assertDatabaseCount('stock_opname_discovered_overrides', 0);
    }
}
