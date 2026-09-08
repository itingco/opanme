<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('source_database', 40)->nullable()->after('role');
            $table->unsignedBigInteger('erp_warehouse_id')->nullable()->after('source_database');
            $table->string('warehouse_code', 100)->nullable()->after('erp_warehouse_id');
            $table->string('warehouse_name', 255)->nullable()->after('warehouse_code');
            $table->index(['source_database', 'erp_warehouse_id'], 'users_gerai_warehouse_index');
        });

        if (! filter_var(env('APPHUB_DROP_LOCAL_MASTERS', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $this->assertAppHubMasterExists('itembarcode');
        $this->assertAppHubMasterExists('itemratio');

        Schema::dropIfExists('item_barcodes');
        Schema::dropIfExists('uom_ratios');
    }

    private function assertAppHubMasterExists(string $table): void
    {
        $row = DB::connection('apphub')->selectOne(
            'SELECT OBJECT_ID(?, ?) AS object_id',
            ['dbo.'.$table, 'U']
        );

        if (! $row || ! $row->object_id) {
            throw new RuntimeException("DB_AppHub.dbo.{$table} tidak ditemukan. Cleanup PostgreSQL dibatalkan.");
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_gerai_warehouse_index');
            $table->dropColumn(['source_database', 'erp_warehouse_id', 'warehouse_code', 'warehouse_name']);
        });
    }
};
