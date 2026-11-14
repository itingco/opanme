<?php
namespace App\Http\Middleware;

use App\Services\MenuPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function __construct(private MenuPermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        $userRole = $this->permissions->canonicalRole((string) $user->role);
        $allowedRoles = array_map(
            fn (string $role): string => $this->permissions->canonicalRole($role),
            $roles
        );

        if (in_array($userRole, $allowedRoles, true)) {
            return $next($request);
        }

        $route = $request->route()?->getName();

        /*
         * Endpoint /admin/erp/warehouses dipakai oleh halaman Buat Cycle dan
         * halaman User & Akses. Route ini masih berada di group role:ADMIN,
         * sehingga role seperti ADMIN_GUDANG akan mendapat 403 walaupun
         * permission menu Cycle Opname sudah diberikan.
         *
         * Izinkan endpoint warehouse hanya bila user memang mempunyai hak
         * Cycle Opname atau User & Akses. Ini sengaja dicek langsung di sini
         * agar tidak tergantung config cache/support-route mapping.
         */
        if ($route === 'admin.erp.warehouses') {
            if (
                $this->permissions->allowed($userRole, 'cycles')
                || $this->permissions->allowed($userRole, 'users')
            ) {
                return $next($request);
            }
        }

        // Endpoint pendukung lain yang didefinisikan dari config.
        if ($route) {
            $supportMenus = config('menu_permissions.support_routes.'.$route, []);
            foreach ($supportMenus as $supportMenu) {
                if ($this->permissions->allowed($userRole, (string) $supportMenu)) {
                    return $next($request);
                }
            }
        }

        // Menu yang dikonfigurasi dapat meng-override pembatasan role lama.
        $menu = $this->permissions->menuForRoute($route);
        if ($menu !== null && $this->permissions->allowed($userRole, $menu)) {
            return $next($request);
        }

        abort(403);
    }
}
