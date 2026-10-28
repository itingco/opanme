<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MenuPermissionService
{
    private ?array $matrix = null;

    public function menuForRoute(?string $name): ?string
    {
        if (!$name) return null;
        foreach (config('menu_permissions.menus', []) as $key => $menu) {
            foreach ($menu['match'] as $pattern) {
                if (Str::is($pattern, $name)) return $key;
            }
        }
        return null;
    }

    public function allowed(string $role, string $menu): bool
    {
        if (!array_key_exists($menu, config('menu_permissions.menus', []))) return false;
        if (!in_array($role, config('menu_permissions.roles', []), true)) return false;
        $matrix = $this->matrix();
        return (bool) ($matrix[$role][$menu] ?? in_array($menu, config('menu_permissions.defaults.'.$role, []), true));
    }

    public function matrix(): array
    {
        if ($this->matrix !== null) return $this->matrix;
        $this->matrix = [];
        // Until migration has run, preserve the existing role defaults instead of breaking login.
        if (!Schema::hasTable('role_menu_permissions')) return $this->matrix;
        foreach (DB::table('role_menu_permissions')->get(['role','menu_key','allowed']) as $row) {
            $this->matrix[$row->role][$row->menu_key] = (bool) $row->allowed;
        }
        return $this->matrix;
    }

    public function firstAllowedRoute(string $role): ?string
    {
        foreach (config('menu_permissions.menus', []) as $key => $menu) {
            if ($this->allowed($role, $key) && \Illuminate\Support\Facades\Route::has($menu['route'])) {
                return $menu['route'];
            }
        }
        return null;
    }
}
