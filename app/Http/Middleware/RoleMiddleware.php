<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        // Cetak Label QR adalah fitur bersama ADMIN, ADMIN_GUDANG,
        // dan CHECKER_GUDANG. Pengecualian ini hanya berlaku pada
        // route label.*, bukan pada route admin.* lainnya.
        if (
            $request->routeIs('label.*')
            && in_array($user->role, [
                User::ROLE_ADMIN,
                User::ROLE_ADMIN_GUDANG,
                User::ROLE_CHECKER_GUDANG,
            ], true)
        ) {
            return $next($request);
        }

        if (! in_array($user->role, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
