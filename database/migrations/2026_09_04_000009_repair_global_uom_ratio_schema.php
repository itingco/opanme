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
            $this->createGlobalTable();
            return;
        }

        if (! Schema::hasColumn('uom_ratios', 'item_code')
            || ! Schema::hasColumn('uom_ratios', 'uom_code')
            || ! Schema::hasColumn('uom_ratios', 'ratio')) {
            throw new \RuntimeException('Tabel uom_ratios tidak memiliki kolom ItemCode/UOM/Ratio yang diperlukan.');
        }

        $hasLegacyColumns = Schema::hasColumn('uom_ratios', 'source_database')
            || Schema::hasColumn('uom_ratios', 'item_id')
            || Schema::hasColumn('uom_ratios', 'uom_level')
            || Schema::hasColumn('uom_ratios', 'item_name');

        if ($hasLegacyColumns) {
            $this->rebuildLegacyTable();
            return;
        }

        $this->normalizeAndDeduplicateCurrentTable();
        $this->ensureUniqueIndex();
    }

    private function rebuildLegacyTable(): void
    {
        $backupTable = 'uom_ratios_before_global_repair';
        Schema::dropIfExists($backupTable);

        $rows = DB::table('uom_ratios')
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get();

        Schema::rename('uom_ratios', $backupTable);
        $this->createGlobalTable();

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

        Schema::drop($backupTable);
    }

    private function normalizeAndDeduplicateCurrentTable(): void
    {
        $rows = DB::table('uom_ratios')
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get(['id', 'item_code', 'uom_code']);

        $keepByKey = [];
        $normalizedById = [];

        foreach ($rows as $row) {
            $itemCode = strtoupper(trim((string) $row->item_code));
            $uomCode = strtoupper(trim((string) $row->uom_code));

            if ($itemCode === '' || $uomCode === '') {
                continue;
            }

            $key = $itemCode.'|'.$uomCode;
            $keepByKey[$key] = $row->id; // orderBy makes the newest row win.
            $normalizedById[$row->id] = [$itemCode, $uomCode];
        }

        $keepIds = array_values($keepByKey);
        foreach ($rows as $row) {
            if (! in_array($row->id, $keepIds, true)) {
                DB::table('uom_ratios')->where('id', $row->id)->delete();
            }
        }

        foreach ($keepByKey as $key => $id) {
            [$itemCode, $uomCode] = $normalizedById[$id];
            DB::table('uom_ratios')->where('id', $id)->update([
                'item_code' => $itemCode,
                'uom_code' => $uomCode,
            ]);
        }
    }

    private function createGlobalTable(): void
    {
        Schema::create('uom_ratios', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 100);
            $table->string('uom_code', 50);
            $table->decimal('ratio', 28, 4);
            $table->timestamps();
            $table->unique(['item_code', 'uom_code'], 'uom_ratios_item_uom_unique');
            $table->index('item_code');
        });
    }

    private function ensureUniqueIndex(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS "uom_ratios_item_uom_unique" '
                .'ON "uom_ratios" ("item_code", "uom_code")'
            );
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS uom_ratios_item_uom_unique '
                .'ON uom_ratios (item_code, uom_code)'
            );
            return;
        }

        Schema::table('uom_ratios', function (Blueprint $table) {
            $table->unique(['item_code', 'uom_code'], 'uom_ratios_item_uom_unique');
        });
    }

    public function down(): void
    {
        // Repair migration intentionally keeps the global ItemCode + UOM design.
    }
};
