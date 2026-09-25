<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_warehouse_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_database', 40);
            $table->unsignedBigInteger('erp_warehouse_id');
            $table->string('warehouse_code', 100);
            $table->string('warehouse_name', 255);
            $table->timestamps();

            $table->unique(
                ['user_id', 'source_database', 'erp_warehouse_id'],
                'user_warehouse_assignment_unique'
            );
            $table->index(
                ['source_database', 'erp_warehouse_id'],
                'user_warehouse_assignment_erp_index'
            );
        });

        // Preserve old single GERAI binding as the first multi-warehouse assignment.
        DB::table('users')
            ->whereNotNull('source_database')
            ->whereNotNull('erp_warehouse_id')
            ->orderBy('id')
            ->get(['id', 'source_database', 'erp_warehouse_id', 'warehouse_code', 'warehouse_name', 'created_at', 'updated_at'])
            ->each(function ($user): void {
                DB::table('user_warehouse_assignments')->updateOrInsert([
                    'user_id' => $user->id,
                    'source_database' => $user->source_database,
                    'erp_warehouse_id' => $user->erp_warehouse_id,
                ], [
                    'warehouse_code' => $user->warehouse_code ?: '-',
                    'warehouse_name' => $user->warehouse_name ?: '-',
                    'created_at' => $user->created_at ?? now(),
                    'updated_at' => $user->updated_at ?? now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_warehouse_assignments');
    }
};
