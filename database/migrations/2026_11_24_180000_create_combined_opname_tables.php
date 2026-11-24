<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cycle_combined_sources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cycle_id')->constrained('stock_opname_cycles')->cascadeOnDelete();
            $t->string('source_database', 30);
            $t->bigInteger('erp_warehouse_id');
            $t->string('warehouse_code', 100);
            $t->string('warehouse_name');
            $t->unique(['cycle_id', 'source_database', 'erp_warehouse_id'], 'combined_source_unique');
        });
        Schema::create('cycle_combined_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cycle_id')->constrained('stock_opname_cycles')->cascadeOnDelete();
            $t->string('item_code', 100);
            $t->string('item_name')->nullable();
            $t->string('uom_code', 50);
            $t->unique(['cycle_id', 'item_code']);
        });
        Schema::create('cycle_combined_stocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('combined_item_id')->constrained('cycle_combined_items')->cascadeOnDelete();
            $t->foreignId('combined_source_id')->constrained('cycle_combined_sources')->cascadeOnDelete();
            $t->bigInteger('erp_item_id');
            $t->decimal('opening_qty', 28, 4)->default(0);
            $t->decimal('closing_qty', 28, 4)->nullable();
            $t->unique(['combined_item_id', 'combined_source_id'], 'combined_stock_unique');
        });
    }
    public function down(): void
    {
        // Never silently discard the source snapshots of an active/finished cycle.
        if (\Illuminate\Support\Facades\DB::table('stock_opname_cycles')->where('source_database', 'COMBINED')->exists()) {
            throw new \RuntimeException('Rollback ditolak: masih ada Cycle Opname COMBINED.');
        }
        Schema::dropIfExists('cycle_combined_stocks');
        Schema::dropIfExists('cycle_combined_items');
        Schema::dropIfExists('cycle_combined_sources');
    }
};
