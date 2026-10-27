<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sample_cycle_checkers')) {
            Schema::create('sample_cycle_checkers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('sample_cycle_id')->constrained('sample_cycles')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['sample_cycle_id', 'user_id'], 'sample_cycle_checkers_cycle_user_unique');
                $table->index('user_id', 'sample_cycle_checkers_user_index');
            });
        }

        // Backward compatibility: every old warehouse sampling period keeps its
        // previous assigned_checker_id as the first member of the checker team.
        DB::table('sample_cycles')
            ->where('cycle_type', 'WAREHOUSE')
            ->whereNotNull('assigned_checker_id')
            ->orderBy('id')
            ->chunkById(500, function ($cycles): void {
                $now = now();
                $rows = $cycles->map(static fn ($cycle): array => [
                    'sample_cycle_id' => (int) $cycle->id,
                    'user_id' => (int) $cycle->assigned_checker_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows !== []) {
                    DB::table('sample_cycle_checkers')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_cycle_checkers');
    }
};
