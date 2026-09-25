<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (! Auth::guard($guard)->check()) continue;

            /** @var User $user */
            $user = Auth::guard($guard)->user();
            $route = match ($user->role) {
                User::ROLE_ADMIN => 'admin.dashboard',
                User::ROLE_ADMIN_GERAI => 'gerai.admin.home',
                User::ROLE_CHECKER_GERAI, User::ROLE_GERAI => 'gerai.checker.home',
                User::ROLE_ADMIN_GUDANG => 'warehouse.admin.index',
                User::ROLE_CHECKER_GUDANG => 'warehouse.checker.index',
                default => 'checker.home',
            };

            return redirect()->route($route);
        }

        return $next($request);
    }
}
