<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('uom_ratios')) {
            Schema::create('uom_ratios', function (Blueprint $t) {
                $t->id();
                $t->string('item_code', 100);
                $t->string('uom_code', 50);
                $t->decimal('ratio', 28, 4);
                $t->timestamps();
                $t->unique(['item_code', 'uom_code'], 'uom_ratios_item_uom_unique');
                $t->index('item_code');
            });

            return;
        }

        $legacyTable = 'uom_ratios_before_global';
        Schema::dropIfExists($legacyTable);

        $rows = DB::table('uom_ratios')
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get();

        Schema::rename('uom_ratios', $legacyTable);

        Schema::create('uom_ratios', function (Blueprint $t) {
            $t->id();
            $t->string('item_code', 100);
            $t->string('uom_code', 50);
            $t->decimal('ratio', 28, 4);
            $t->timestamps();
            $t->unique(['item_code', 'uom_code'], 'uom_ratios_item_uom_unique');
            $t->index('item_code');
        });

        $deduplicated = [];
        foreach ($rows as $row) {
            $itemCode = strtoupper(trim((string) ($row->item_code ?? '')));
            $uomCode = strtoupper(trim((string) ($row->uom_code ?? '')));

            if ($itemCode === '' || $uomCode === '') {
                continue;
            }

            $deduplicated[$itemCode.'|'.$uomCode] = [
                'item_code' => $itemCode,
                'uom_code' => $uomCode,
                'ratio' => (float) $row->ratio,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ];
        }

        foreach (array_chunk(array_values($deduplicated), 500) as $chunk) {
            DB::table('uom_ratios')->insert($chunk);
        }

        Schema::drop($legacyTable);
    }

    public function down(): void
    {
        throw new \RuntimeException(
            'Migration global UOM ratio tidak dapat di-rollback otomatis karena informasi database/item_id/uom_level lama sudah disederhanakan. Restore backup database bila perlu kembali ke struktur lama.'
        );
    }
};
