<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class MenuPermissionService
{
    private ?array $matrix = null;

    public function canonicalRole(string $role): string
    {
        return match (strtoupper(trim($role))) {
            'GERAI', 'CHECKER' => 'CHECKER_GERAI',
            default => strtoupper(trim($role)),
        };
    }

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
        $role = $this->canonicalRole($role);

        if (!array_key_exists($menu, config('menu_permissions.menus', []))) return false;

        // Penggabungan Checker Gerai + legacy adalah aturan tetap, bukan permission opsional.
        if ($role === 'CHECKER_GERAI' && in_array($menu, ['gerai_checker', 'legacy_checker'], true)) {
            return true;
        }

        if (!in_array($role, config('menu_permissions.roles', []), true)) return false;

        $matrix = $this->matrix();
        return (bool) (
            $matrix[$role][$menu]
            ?? in_array($menu, config('menu_permissions.defaults.'.$role, []), true)
        );
    }

    public function matrix(): array
    {
        if ($this->matrix !== null) return $this->matrix;
        $this->matrix = [];

        // Sebelum migration permission tersedia, pakai defaults agar login tidak putus.
        if (!Schema::hasTable('role_menu_permissions')) return $this->matrix;

        $activeRoles = config('menu_permissions.roles', []);
        foreach (DB::table('role_menu_permissions')->get(['role','menu_key','allowed']) as $row) {
            $role = $this->canonicalRole((string) $row->role);
            if (!in_array($role, $activeRoles, true)) continue;

            // Permission role baru lebih diutamakan. Row legacy hanya menjadi fallback.
            if (!isset($this->matrix[$role][$row->menu_key]) || in_array((string) $row->role, $activeRoles, true)) {
                $this->matrix[$role][$row->menu_key] = (bool) $row->allowed;
            }
        }
        return $this->matrix;
    }

    public function firstAllowedRoute(string $role): ?string
    {
        // Akun CHECKER lama tetap masuk ke halaman cycle legacy sampai user tersebut
        // diedit/disimpan ulang sebagai CHECKER_GERAI. Hak aksesnya sudah digabung.
        if (strtoupper(trim($role)) === 'CHECKER' && Route::has('checker.home')) {
            return 'checker.home';
        }

        foreach (config('menu_permissions.menus', []) as $key => $menu) {
            if ($this->allowed($role, $key) && Route::has($menu['route'])) {
                return $menu['route'];
            }
        }
        return null;
    }
}
