<?php

namespace Tests\Feature;

use App\Services\CombinedCycleCatalogService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CombinedCycleCatalogTest extends TestCase
{
    public function test_smallest_uom_uses_last_configured_level_without_pcs_fallback(): void
    {
        foreach (['erp_ingco', 'erp_smi'] as $name) {
            config(['database.connections.'.$name => ['driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'']]);
            DB::purge($name);
            $db = DB::connection($name);
            $db->statement('CREATE TABLE IC_UOM (UOMID INTEGER PRIMARY KEY, UOMCode VARCHAR(20))');
            $db->statement('CREATE TABLE IC_Items (ItemID INTEGER PRIMARY KEY, ItemCode VARCHAR(100), ItemName VARCHAR(100), UOMID1 INTEGER, UOMID2 INTEGER, UOMID3 INTEGER, UOMID4 INTEGER)');
            $db->statement("INSERT INTO IC_UOM VALUES (1, 'PCS'), (5, 'KRTN'), (3, 'KTK'), (2, 'SET')");
        }
        DB::connection('erp_ingco')->statement("INSERT INTO IC_Items VALUES (16583,'AB6038','Blower',5,1,0,0), (10,'THREE','Three',5,3,1,0), (11,'FOUR','Four',5,3,2,1), (12,'SETONLY','Set',2,0,0,0), (13,'INVALID','Invalid',5,999,0,0), (14,'EMPTY','Empty',0,0,0,0), (15,'NULLLEVEL','Null',5,1,NULL,NULL)");
        DB::connection('erp_smi')->statement("INSERT INTO IC_Items VALUES (5361,'AB6038','Blower',1,0,0,0)");
        $catalog = app(CombinedCycleCatalogService::class);
        $codes = ['AB6038','THREE','FOUR','SETONLY','INVALID','EMPTY','NULLLEVEL'];
        $ingco = collect($catalog->findItemsByCodes('AS_INGCO', $codes))->keyBy('item_code');
        $smi = collect($catalog->findItemsByCodes('AS_SMI', ['AB6038']))->keyBy('item_code');
        $this->assertSame('PCS', $ingco['AB6038']['uom_code']);
        $this->assertSame('PCS', $smi['AB6038']['uom_code']);
        $this->assertSame(16583, $ingco['AB6038']['item_id']);
        $this->assertSame(5361, $smi['AB6038']['item_id']);
        foreach (['THREE','FOUR','NULLLEVEL'] as $code) $this->assertSame('PCS', $ingco[$code]['uom_code']);
        $this->assertSame('SET', $ingco['SETONLY']['uom_code']);
        foreach (['INVALID','EMPTY'] as $code) $this->assertSame('', $ingco[$code]['uom_code']);
    }
}
