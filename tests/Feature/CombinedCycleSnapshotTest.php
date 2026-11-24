<?php

namespace Tests\Feature;

use App\Models\StockOpnameCycle;
use App\Models\StockSnapshot;
use App\Models\User;
use App\Services\CombinedCycleSnapshotService;
use App\Services\CycleSnapshotService;
use App\Services\ErpCatalogService;
use App\Services\ErpStockService;
use App\Services\CombinedCycleCatalogService;
use App\Services\CombinedCycleAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CombinedCycleSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_combined_cycle_handles_shared_warehouse_ids_and_different_item_ids(): void
    {
        $admin=User::factory()->create(['role'=>User::ROLE_ADMIN]);
        $erp=$this->mock(ErpCatalogService::class);
        foreach (['AS_INGCO','AS_SMI'] as $db) $erp->shouldReceive('warehouses')->once()->with($db)->andReturn([
            ['warehouse_id'=>5,'warehouse_code'=>'WH-5','warehouse_name'=>'Gudang '.$db],
        ]);
        $this->actingAs($admin)->post(route('admin.cycles.store'),[
            'source_database'=>'COMBINED','cutoff_date'=>'2026-11-24','combined_warehouses'=>['AS_INGCO:5','AS_SMI:5'],
        ])->assertSessionHasNoErrors();
        $cycle=StockOpnameCycle::query()->where('source_database','COMBINED')->sole();
        $this->assertCount(1,$cycle->warehouses);
        $this->assertSame(2,DB::table('cycle_combined_sources')->where('cycle_id',$cycle->id)->count());
        $stock=$this->mock(ErpStockService::class);
        $row=fn($id,$code,$qty)=>['item_id'=>$id,'item_code'=>$code,'item_name'=>$code,'warehouse_id'=>5,'smallest_on_hand'=>$qty];
        $stock->shouldReceive('snapshot')->with('AS_INGCO',\Mockery::any())->twice()->andReturn([$row(100,'A',60),$row(200,'B',5)],[$row(100,'A',55),$row(200,'B',5)]);
        // ERP ID 100 in SMI represents a DIFFERENT code; A is 900.
        $stock->shouldReceive('snapshot')->with('AS_SMI',\Mockery::any())->twice()->andReturn([$row(900,'A',40),$row(100,'C',6)],[$row(900,'A',42),$row(100,'C',6)]);
        $catalog=$this->mock(CombinedCycleCatalogService::class);
        $master=fn($id,$code)=>['item_id'=>$id,'item_code'=>$code,'item_name'=>$code,'uom_code'=>'PCS'];
        $catalog->shouldReceive('findItemsByCodes')->with('AS_INGCO',\Mockery::any())->twice()->andReturn([$master(100,'A'),$master(200,'B')]);
        $catalog->shouldReceive('findItemsByCodes')->with('AS_SMI',\Mockery::any())->twice()->andReturn([$master(900,'A'),$master(100,'C')]);
        $service=app(CycleSnapshotService::class);
        $this->assertInstanceOf(CombinedCycleSnapshotService::class,$service);
        $this->assertSame(3,$service->captureOpening($cycle,now()));
        $snapshots=StockSnapshot::where('cycle_id',$cycle->id)->get()->keyBy('item_code');
        $this->assertEquals(100,(float)$snapshots['A']->opening_system_qty);
        $this->assertNotEquals($snapshots['A']->item_id,$snapshots['C']->item_id);
        $cycle->update(['status'=>'CLOSED','started_at'=>now()->subHour(),'completed_at'=>now()]);
        $service->captureClosing($cycle,now());
        $this->assertEquals(97,(float)$snapshots['A']->fresh()->closing_system_qty);
        $this->assertEquals(100,(float)$snapshots['A']->fresh()->opening_system_qty);
        $result=app(CombinedCycleAllocationService::class)->details($cycle,[(object)[
            'row_type'=>'ERP','item_id'=>$snapshots['A']->item_id,'final_physical_qty'=>90,'smallest_uom_code'=>null,
        ]]);
        $this->assertEquals([54,36],array_column($result[0]['details'],'allocated'));
        $this->assertEquals([60,40],array_column($result[0]['details'],'opening'));
        $this->assertEquals([55,42],array_column($result[0]['details'],'closing'));
    }
}
