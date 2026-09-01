<?php

namespace Tests\Feature;

use App\Models\CycleWarehouse;
use App\Models\StockOpnameCycle;
use App\Models\StockSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CycleFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_cycle_with_successful_closing_snapshot_can_be_finalized(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $cycle = StockOpnameCycle::factory()->create([
            'status' => StockOpnameCycle::STATUS_CLOSED,
            'completed_at' => now()->subMinute(),
            'closing_snapshot_at' => now()->subMinute(),
            'closing_snapshot_error' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.cycles.finalize', $cycle), ['confirm_finalization' => '1'])
            ->assertRedirect(route('admin.cycles.summary', $cycle));

        $this->assertDatabaseHas('stock_opname_cycles', [
            'id' => $cycle->id,
            'status' => StockOpnameCycle::STATUS_FINALIZED,
            'finalized_by' => $admin->id,
        ]);
    }

    public function test_cycle_cannot_be_finalized_when_closing_snapshot_failed(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $cycle = StockOpnameCycle::factory()->create([
            'status' => StockOpnameCycle::STATUS_CLOSED,
            'completed_at' => now(),
            'closing_snapshot_at' => null,
            'closing_snapshot_error' => 'ERP timeout',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.cycles.summary', $cycle))
            ->post(route('admin.cycles.finalize', $cycle), ['confirm_finalization' => '1'])
            ->assertSessionHasErrors('finalize');

        $this->assertDatabaseHas('stock_opname_cycles', [
            'id' => $cycle->id,
            'status' => StockOpnameCycle::STATUS_CLOSED,
        ]);
    }

    public function test_override_is_rejected_after_cycle_is_finalized(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $cycle = StockOpnameCycle::factory()->create([
            'status' => StockOpnameCycle::STATUS_FINALIZED,
            'completed_at' => now()->subHour(),
            'closing_snapshot_at' => now()->subHour(),
            'finalized_at' => now(),
            'finalized_by' => $admin->id,
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
            'opening_snapshot_at' => now()->subHour(),
            'closing_snapshot_at' => now()->subHour(),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.cycles.summary.override', [$cycle, $warehouse->id, 1408]), [
                'override_qty' => 9,
                'comment' => 'Tidak boleh berubah setelah final.',
            ])
            ->assertSessionHasErrors('override');
    }

    public function test_final_report_pdf_is_only_available_for_finalized_cycle(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $closed = StockOpnameCycle::factory()->create([
            'status' => StockOpnameCycle::STATUS_CLOSED,
            'completed_at' => now(),
            'closing_snapshot_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.cycles.final-report', $closed))
            ->assertSessionHasErrors('report');
    }
}
