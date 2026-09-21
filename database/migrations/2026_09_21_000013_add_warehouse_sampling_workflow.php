<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_cycles', function (Blueprint $table) {
            $table->string('cycle_type', 20)->default('GERAI')->index();
            $table->decimal('target_percentage', 5, 2)->default(100);
            $table->foreignId('assigned_checker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
        });

        Schema::create('sample_cycle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_cycle_id')->constrained('sample_cycles')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->unsignedBigInteger('item_id');
            $table->string('item_code', 100);
            $table->string('item_name', 255);
            $table->string('uom_code', 50)->default('PCS');
            $table->decimal('system_qty', 28, 4)->default(0);
            $table->decimal('physical_qty', 28, 4)->nullable();
            $table->string('result', 20)->nullable()->index();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable()->index();
            $table->timestamps();

            // line_no is unique inside a period, item_id intentionally is NOT unique.
            // This permits the same ERP item to appear repeatedly in one period.
            $table->unique(['sample_cycle_id', 'line_no'], 'sample_cycle_items_line_unique');
            $table->index(['sample_cycle_id', 'item_id'], 'sample_cycle_items_item_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_cycle_items');

        Schema::table('sample_cycles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_checker_id');
            $table->dropColumn(['cycle_type', 'target_percentage', 'notes']);
        });
    }
};
