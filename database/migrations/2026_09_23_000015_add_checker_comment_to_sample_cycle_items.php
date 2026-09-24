<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_cycle_items', function (Blueprint $table) {
            $table->text('checker_comment')->nullable()->after('physical_qty');
        });
    }

    public function down(): void
    {
        Schema::table('sample_cycle_items', function (Blueprint $table) {
            $table->dropColumn('checker_comment');
        });
    }
};
