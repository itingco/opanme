<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('role_menu_permissions')) {
            Schema::create('role_menu_permissions', function (Blueprint $table) {
                $table->id();
                $table->string('role', 32);
                $table->string('menu_key', 64);
                $table->boolean('allowed')->default(false);
                $table->timestamp('updated_at')->nullable();
                $table->unique(['role','menu_key']);
            });
        }
        foreach (config('menu_permissions.roles', []) as $role) {
            foreach (array_keys(config('menu_permissions.menus', [])) as $key) {
                DB::table('role_menu_permissions')->updateOrInsert(
                    ['role' => $role, 'menu_key' => $key],
                    ['allowed' => in_array($key, config('menu_permissions.defaults.'.$role, []), true), 'updated_at' => now()]
                );
            }
        }
    }
    public function down(): void { Schema::dropIfExists('role_menu_permissions'); }
};
