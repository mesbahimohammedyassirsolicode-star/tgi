<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $user = $request->user();
        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }
        // Fallback: when role_user is empty, use denormalized user.role (admin → directeur for back compat)
        $slugFromRole = match ($user->role) {
            'admin' => 'directeur',
            default => $user->role,
        };
        if ($slugFromRole && in_array($slugFromRole, $roles)) {
            return $next($request);
        }
        // Allow user.role === 'admin' when route explicitly allows 'admin' (e.g. users management)
        if ($user->role === 'admin' && in_array('admin', $roles)) {
            return $next($request);
        }
        return response()->json(['message' => 'Accès refusé.'], 403);
    }
}
