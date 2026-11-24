<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cycle_combined_manual_allocations', function (Blueprint $t) {
            $t->foreignId('combined_item_id')->primary()->constrained('cycle_combined_items')->cascadeOnDelete();
            $t->decimal('physical_qty', 28, 4);
            $t->text('allocations'); // JSON map: source ID => qty in smallest UOM (decimal string)
            $t->string('note', 1000);
            $t->foreignId('updated_by')->constrained('users');
            $t->timestampTz('updated_at');
        });
    }
    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('cycle_combined_manual_allocations')->exists()) {
            throw new \RuntimeException('Rollback ditolak: masih ada alokasi manual tersimpan.');
        }
        Schema::dropIfExists('cycle_combined_manual_allocations');
    }
};
