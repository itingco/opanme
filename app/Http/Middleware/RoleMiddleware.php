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

        if (in_array($userRole, $allowedRoles, true)) return $next($request);

        $route = $request->route()?->getName();
        // Menu yang dikonfigurasi dapat meng-override pembatasan role lama.
        $menu = $this->permissions->menuForRoute($route);
        if ($menu !== null && $this->permissions->allowed($userRole, $menu)) return $next($request);

        abort(403);
    }
}
