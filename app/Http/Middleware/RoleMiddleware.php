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
        if (in_array($user->role, $roles, true)) return $next($request);
        $route = $request->route()?->getName();
        // Only explicitly configured menu route groups can override old hard-coded roles.
        $menu = $this->permissions->menuForRoute($route);
        if ($menu !== null && $this->permissions->allowed((string)$user->role, $menu)) return $next($request);
        abort(403);
    }
}
