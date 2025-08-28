<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
 public function handle(Request $request, Closure $next, ...$roles)
{
    $user = $request->user();

    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    if (!$user->role || !in_array($user->role->name, $roles)) {
        return response()->json([
            'message' => 'Unauthorized',
            'required_roles' => $roles,
            'user_role' => $user->role ? $user->role->name : null
        ], 403);
    }

    return $next($request);
}
}
