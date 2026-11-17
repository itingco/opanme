<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_opname_override_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('stock_opname_cycles')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('cycle_warehouses')->cascadeOnDelete();
            $table->bigInteger('item_id');
            $table->string('action', 20);
            $table->string('source', 20);
            $table->uuid('batch_id')->nullable();
            $table->decimal('previous_qty', 28, 4)->nullable();
            $table->decimal('input_qty', 28, 4);
            $table->decimal('result_qty', 28, 4);
            $table->text('comment');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamp('created_at');
            $table->index(['cycle_id', 'warehouse_id', 'item_id'], 'override_history_item_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('stock_opname_override_history'); }
};
