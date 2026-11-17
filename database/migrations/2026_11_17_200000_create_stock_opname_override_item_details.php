<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_opname_override_item_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('stock_opname_cycles')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('cycle_warehouses')->cascadeOnDelete();
            $table->bigInteger('item_id');
            $table->string('item_code', 150);
            $table->string('item_name', 500);
            $table->timestamps();
            $table->unique(['cycle_id', 'warehouse_id', 'item_id'], 'override_item_details_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('stock_opname_override_item_details'); }
};
