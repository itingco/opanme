<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('cycle_no', 40)->unique();
            $table->foreignId('created_by')->constrained('users');
            $table->string('source_database', 40);
            $table->unsignedBigInteger('erp_warehouse_id');
            $table->string('warehouse_code', 100);
            $table->string('warehouse_name', 255);
            $table->string('location', 255);
            $table->string('status', 20)->default('OPEN')->index();
            $table->timestamp('started_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['source_database', 'erp_warehouse_id', 'started_at'], 'sample_cycles_wh_date_index');
        });

        Schema::create('sample_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_cycle_id')->constrained('sample_cycles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('source_database', 40);
            $table->unsignedBigInteger('erp_warehouse_id');
            $table->string('warehouse_code', 100);
            $table->string('warehouse_name', 255);
            $table->string('location', 255);
            $table->string('barcode', 150);
            $table->unsignedBigInteger('item_id');
            $table->string('item_code', 100);
            $table->string('item_name', 255);
            $table->string('uom_code', 50);
            $table->decimal('system_qty', 28, 4);
            $table->decimal('physical_qty', 28, 4);
            $table->string('result', 20)->index();
            $table->timestamp('scanned_at')->index();
            $table->timestamps();
            $table->unique(['sample_cycle_id', 'item_id'], 'sample_checks_cycle_item_unique');
            $table->index(['source_database', 'erp_warehouse_id', 'item_code', 'scanned_at'], 'sample_checks_history_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_checks');
        Schema::dropIfExists('sample_cycles');
    }
};
