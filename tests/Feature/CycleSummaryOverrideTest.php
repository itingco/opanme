<?php

namespace Tests\Feature;

use App\Models\CycleWarehouse;
use App\Models\StockOpnameCycle;
use App\Models\StockSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CycleSummaryOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_override_with_required_comment_for_closed_cycle(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $cycle = StockOpnameCycle::factory()->create([
            'status' => StockOpnameCycle::STATUS_CLOSED,
            'completed_at' => now(),
        ]);
        $warehouse = CycleWarehouse::factory()->create(['cycle_id' => $cycle->id]);

        StockSnapshot::create([
            'cycle_id' => $cycle->id,
            'warehouse_id' => $warehouse->id,
            'erp_warehouse_id' => $warehouse->erp_warehouse_id,
            'warehouse_code' => $warehouse->warehouse_code,
            'item_id' => 1408,
            'item_code' => 'AAC1408',
            'item_name' => 'KOMPRESOR MINI',
            'opening_system_qty' => 10,
            'closing_system_qty' => 10,
            'opening_snapshot_at' => now(),
            'closing_snapshot_at' => now(),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.cycles.summary.override', [$cycle, $warehouse->id, 1408]), [
                'override_qty' => 9,
                'comment' => 'Barang rusak dihitung manual setelah verifikasi admin.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('stock_opname_overrides', [
            'cycle_id' => $cycle->id,
            'warehouse_id' => $warehouse->id,
            'item_id' => 1408,
            'override_qty' => '9.0000',
            'updated_by' => $admin->id,
        ]);
    }

    public function test_override_is_rejected_without_comment(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $cycle = StockOpnameCycle::factory()->create([
            'status' => StockOpnameCycle::STATUS_CLOSED,
            'completed_at' => now(),
        ]);
        $warehouse = CycleWarehouse::factory()->create(['cycle_id' => $cycle->id]);

        StockSnapshot::create([
            'cycle_id' => $cycle->id,
            'warehouse_id' => $warehouse->id,
            'erp_warehouse_id' => $warehouse->erp_warehouse_id,
            'warehouse_code' => $warehouse->warehouse_code,
            'item_id' => 1408,
            'item_code' => 'AAC1408',
            'item_name' => 'KOMPRESOR MINI',
            'opening_system_qty' => 10,
            'opening_snapshot_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.cycles.summary', $cycle))
            ->put(route('admin.cycles.summary.override', [$cycle, $warehouse->id, 1408]), [
                'override_qty' => 9,
                'comment' => '',
            ])
            ->assertSessionHasErrors('comment');
    }

    public function test_override_cannot_be_saved_while_cycle_is_open(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $cycle = StockOpnameCycle::factory()->create([
            'status' => StockOpnameCycle::STATUS_OPEN,
            'started_at' => now(),
        ]);
        $warehouse = CycleWarehouse::factory()->create(['cycle_id' => $cycle->id]);

        $this->actingAs($admin)
            ->put(route('admin.cycles.summary.override', [$cycle, $warehouse->id, 1408]), [
                'override_qty' => 9,
                'comment' => 'Tidak boleh saat cycle masih berjalan.',
            ])
            ->assertSessionHasErrors('override');

        $this->assertDatabaseCount('stock_opname_overrides', 0);
    }
}
