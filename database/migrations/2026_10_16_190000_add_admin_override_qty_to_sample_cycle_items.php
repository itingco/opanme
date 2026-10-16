<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_cycle_items', function (Blueprint $table) {
            $table->decimal('admin_override_qty', 28, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sample_cycle_items', function (Blueprint $table) {
            $table->dropColumn('admin_override_qty');
        });
    }
};
