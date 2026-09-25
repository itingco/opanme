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
            $table->date('sales_invoice_date')->nullable()->after('system_qty');
            $table->decimal('sales_invoice_qty', 28, 4)->default(0)->after('sales_invoice_date');
            $table->decimal('adjusted_system_qty', 28, 4)->nullable()->after('sales_invoice_qty');
            $table->text('sales_invoice_details')->nullable()->after('adjusted_system_qty');
        });

        DB::table('sample_cycle_item_stocks')
            ->whereNull('adjusted_system_qty')
            ->update([
                'sales_invoice_qty' => 0,
                'adjusted_system_qty' => DB::raw('system_qty'),
            ]);
    }

    public function down(): void
    {
        Schema::table('sample_cycle_item_stocks', function (Blueprint $table) {
            $table->dropColumn([
                'sales_invoice_date',
                'sales_invoice_qty',
                'adjusted_system_qty',
                'sales_invoice_details',
            ]);
        });
    }
};
