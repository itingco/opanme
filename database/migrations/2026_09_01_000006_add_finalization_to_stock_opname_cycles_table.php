<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_opname_cycles', function (Blueprint $table) {
            $table->timestampTz('finalized_at')->nullable()->after('closing_snapshot_error');
            $table->foreignId('finalized_by')->nullable()->after('finalized_at')->constrained('users');
            $table->index(['status', 'finalized_at'], 'stock_opname_cycles_finalized_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stock_opname_cycles', function (Blueprint $table) {
            $table->dropIndex('stock_opname_cycles_finalized_idx');
            $table->dropConstrainedForeignId('finalized_by');
            $table->dropColumn('finalized_at');
        });
    }
};
