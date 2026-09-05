<?php

namespace Tests\Feature;

use App\Models\ItemBarcode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LocalBarcodeMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_barcode_mapping_and_barcode_is_globally_unique(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post('/admin/barcodes', [
            'item_code' => 'item-a',
            'barcode' => '0012345678901',
            'uom_code' => 'pcs',
        ])->assertRedirect();

        $this->actingAs($admin)->post('/admin/barcodes', [
            'item_code' => 'item-b',
            'barcode' => '0012345678901',
            'uom_code' => 'ktk',
        ])->assertRedirect();

        $this->assertSame(1, ItemBarcode::query()->where('barcode', '0012345678901')->count());
        $this->assertDatabaseHas('item_barcodes', [
            'barcode' => '0012345678901',
            'item_code' => 'ITEM-B',
            'uom_code' => 'KTK',
        ]);
    }

    public function test_admin_can_import_itemcode_barcode_uom_csv(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $file = UploadedFile::fake()->createWithContent(
            'barcode.csv',
            "ItemCode,Barcode,UOM\nITEM-A,08990001,PCS\nITEM-A,08990002,KTK\n"
        );

        $this->actingAs($admin)->post('/admin/barcodes/import', [
            'barcode_file' => $file,
        ])->assertRedirect();

        $this->assertDatabaseHas('item_barcodes', [
            'item_code' => 'ITEM-A',
            'barcode' => '08990001',
            'uom_code' => 'PCS',
        ]);
        $this->assertDatabaseHas('item_barcodes', [
            'item_code' => 'ITEM-A',
            'barcode' => '08990002',
            'uom_code' => 'KTK',
        ]);
    }
}
