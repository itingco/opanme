<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opname_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('stock_opname_cycles')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('cycle_warehouses')->cascadeOnDelete();
            $table->bigInteger('item_id');
            $table->decimal('override_qty', 28, 4);
            $table->text('comment');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();

            $table->unique(['cycle_id', 'warehouse_id', 'item_id'], 'stock_opname_override_unique');
            $table->index(['cycle_id', 'updated_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_overrides');
    }
};
