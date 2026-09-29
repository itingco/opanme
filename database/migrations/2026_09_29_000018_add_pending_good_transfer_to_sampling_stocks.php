<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_cycle_item_stocks', function (Blueprint $table) {
            $table->decimal('pending_transfer_qty', 28, 4)->default(0)->after('sales_invoice_details');
            $table->text('pending_transfer_details')->nullable()->after('pending_transfer_qty');
            $table->decimal('validation_system_qty', 28, 4)->nullable()->after('pending_transfer_details');
        });

        DB::table('sample_cycle_item_stocks')
            ->whereNull('validation_system_qty')
            ->update([
                'pending_transfer_qty' => 0,
                'validation_system_qty' => DB::raw('COALESCE(adjusted_system_qty, system_qty)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('sample_cycle_item_stocks', function (Blueprint $table) {
            $table->dropColumn([
                'pending_transfer_qty',
                'pending_transfer_details',
                'validation_system_qty',
            ]);
        });
    }
};
