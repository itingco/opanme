<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_cycle_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_cycle_id')->constrained('sample_cycles')->cascadeOnDelete();
            $table->string('source_database', 40);
            $table->unsignedBigInteger('erp_warehouse_id');
            $table->string('warehouse_code', 100);
            $table->string('warehouse_name', 255);
            $table->timestamps();

            $table->unique(
                ['sample_cycle_id', 'source_database', 'erp_warehouse_id'],
                'sample_cycle_wh_unique'
            );
            $table->index(['source_database', 'erp_warehouse_id'], 'sample_cycle_wh_erp_index');
        });

        Schema::create('sample_cycle_item_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_cycle_item_id')->constrained('sample_cycle_items')->cascadeOnDelete();
            $table->foreignId('sample_cycle_warehouse_id')->constrained('sample_cycle_warehouses')->cascadeOnDelete();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->decimal('system_qty', 28, 4)->default(0);
            $table->decimal('allocated_physical_qty', 28, 4)->nullable();
            $table->string('result', 20)->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['sample_cycle_item_id', 'sample_cycle_warehouse_id'],
                'sample_cycle_item_stock_unique'
            );
        });

        Schema::table('sample_cycle_items', function (Blueprint $table) {
            $table->foreignId('validated_by')->nullable()->after('checked_at')->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable()->after('validated_by')->index();
            $table->text('validation_note')->nullable()->after('validated_at');
        });

        // Backward compatibility: periods made before this migration had only one warehouse.
        $legacyCycles = DB::table('sample_cycles')
            ->where('cycle_type', 'WAREHOUSE')
            ->get(['id', 'source_database', 'erp_warehouse_id', 'warehouse_code', 'warehouse_name', 'created_at', 'updated_at']);

        foreach ($legacyCycles as $cycle) {
            $warehouseId = DB::table('sample_cycle_warehouses')->insertGetId([
                'sample_cycle_id' => $cycle->id,
                'source_database' => $cycle->source_database,
                'erp_warehouse_id' => $cycle->erp_warehouse_id,
                'warehouse_code' => $cycle->warehouse_code,
                'warehouse_name' => $cycle->warehouse_name,
                'created_at' => $cycle->created_at ?? now(),
                'updated_at' => $cycle->updated_at ?? now(),
            ]);

            $items = DB::table('sample_cycle_items')
                ->where('sample_cycle_id', $cycle->id)
                ->get(['id', 'item_id', 'system_qty', 'created_at', 'updated_at']);

            foreach ($items as $item) {
                DB::table('sample_cycle_item_stocks')->insert([
                    'sample_cycle_item_id' => $item->id,
                    'sample_cycle_warehouse_id' => $warehouseId,
                    'item_id' => $item->item_id,
                    'system_qty' => $item->system_qty,
                    'allocated_physical_qty' => null,
                    'result' => null,
                    'created_at' => $item->created_at ?? now(),
                    'updated_at' => $item->updated_at ?? now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('sample_cycle_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validated_by');
            $table->dropColumn(['validated_at', 'validation_note']);
        });

        Schema::dropIfExists('sample_cycle_item_stocks');
        Schema::dropIfExists('sample_cycle_warehouses');
    }
};
