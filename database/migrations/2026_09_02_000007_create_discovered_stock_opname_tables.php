<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opname_discovered_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('stock_opname_cycles')->cascadeOnDelete();
            $table->string('alias_code', 150);
            $table->string('item_name');
            $table->string('default_uom_code', 50)->default('PCS');
            $table->string('smallest_uom_code', 50)->default('PCS');
            $table->decimal('default_ratio_to_smallest', 28, 4)->default(1);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['cycle_id', 'alias_code'], 'stock_opname_discovered_item_barcode_unique');
            $table->index(['cycle_id', 'item_name']);
        });

        Schema::create('discovered_scan_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('cycle_id')->constrained('stock_opname_cycles')->cascadeOnDelete();
            $table->foreignId('discovered_item_id')->constrained('stock_opname_discovered_items')->cascadeOnDelete();
            $table->foreignId('scan_session_id')->constrained('scan_sessions')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('cycle_warehouses')->cascadeOnDelete();
            $table->foreignId('checker_id')->constrained('users');
            $table->string('location');
            $table->decimal('input_qty', 28, 4);
            $table->string('input_uom_code', 50);
            $table->string('smallest_uom_code', 50);
            $table->decimal('ratio_used', 28, 4);
            $table->decimal('physical_qty', 28, 4);
            $table->timestampTz('scanned_at')->index();

            $table->index(['cycle_id', 'warehouse_id', 'discovered_item_id'], 'discovered_scan_summary_idx');
            $table->index(['cycle_id', 'checker_id'], 'discovered_scan_checker_idx');
        });

        Schema::create('stock_opname_discovered_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('stock_opname_cycles')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('cycle_warehouses')->cascadeOnDelete();
            $table->foreignId('discovered_item_id')->constrained('stock_opname_discovered_items')->cascadeOnDelete();
            $table->decimal('input_qty', 28, 4);
            $table->string('input_uom_code', 50);
            $table->string('smallest_uom_code', 50);
            $table->decimal('ratio_used', 28, 4);
            $table->decimal('override_qty', 28, 4);
            $table->text('comment');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();

            $table->unique(
                ['cycle_id', 'warehouse_id', 'discovered_item_id'],
                'stock_opname_discovered_override_unique'
            );
            $table->index(['cycle_id', 'updated_by'], 'discovered_override_audit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_discovered_overrides');
        Schema::dropIfExists('discovered_scan_transactions');
        Schema::dropIfExists('stock_opname_discovered_items');
    }
};
