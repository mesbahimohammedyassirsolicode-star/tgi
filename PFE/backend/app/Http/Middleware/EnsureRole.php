<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowed = [];
        foreach ($roles as $role) {
            foreach (array_map('trim', explode(',', (string) $role)) as $slug) {
                if ($slug !== '') {
                    $allowed[] = $slug;
                }
            }
        }
        $allowed = array_values(array_unique($allowed));
        Log::info('DEBUG EnsureRole', ['path' => $request->path(), 'method' => $request->method(), 'user_role' => $user?->role, 'allowed' => $allowed]);
        if (! $user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        foreach ($allowed as $slug) {
            if ($user->hasRole($slug)) {
                Log::info('DEBUG EnsureRole allow hasRole', ['user_role' => $user->role]);
                return $next($request);
            }
        }
        $slugFromRole = match ($user->role) {
            'admin' => 'directeur',
            default => $user->role,
        };
        if ($slugFromRole && in_array($slugFromRole, $allowed)) {
            Log::info('DEBUG EnsureRole allow fallback', ['user_role' => $user->role]);
            return $next($request);
        }
        if ($user->role === 'admin' && in_array('admin', $allowed)) {
            Log::info('DEBUG EnsureRole allow admin', ['user_role' => $user->role]);
            return $next($request);
        }
        Log::warning('DEBUG EnsureRole DENY 403', ['user_role' => $user->role, 'allowed' => $allowed]);
        return response()->json(['message' => 'Accès refusé.'], 403);
    }
}
