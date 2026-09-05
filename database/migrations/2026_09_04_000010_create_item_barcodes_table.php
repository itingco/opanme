<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_barcodes', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 100);
            $table->string('barcode', 150)->unique();
            $table->string('uom_code', 50);
            $table->timestamps();

            $table->index(['item_code', 'uom_code'], 'item_barcodes_item_uom_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_barcodes');
    }
};
