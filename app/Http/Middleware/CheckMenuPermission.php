<?php
namespace App\Http\Middleware;

use App\Services\MenuPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMenuPermission
{
    public function __construct(private MenuPermissionService $permissions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user) {
            // Home is still hardcoded in the legacy routes/web.php; redirect by the new menu matrix.
            if ($request->is('/') && ($request->isMethod('GET') || $request->isMethod('HEAD'))) {
                $home = $this->permissions->firstAllowedRoute((string)$user->role);
                return $home ? redirect()->route($home) : redirect()->route('password.edit');
            }
            $route = $request->route()?->getName();
            // This page is reserved for ADMIN and cannot be disabled accidentally.
            if ($route && str_starts_with($route, 'admin.menu-permissions.')) {
                abort_unless($user->isAdmin(), 403);
            } else {
                $menu = $this->permissions->menuForRoute($route);
                if ($menu !== null && !$this->permissions->allowed((string)$user->role, $menu)) {
                    abort(403, 'Role Anda tidak memiliki izin untuk membuka menu ini.');
                }
            }
        }
        return $next($request);
    }
}
