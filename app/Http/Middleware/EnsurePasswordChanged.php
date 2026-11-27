<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users flagged must_change_password may only call the endpoints needed to change it.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // A deactivated user's existing session stops working immediately
        if ($user && ! $user->is_active) {
            return response()->json(['message' => 'Your account has been deactivated. Please contact your administrator.'], 403);
        }

        if ($user && $user->must_change_password && ! $request->is('api/me', 'api/logout', 'api/change-password')) {
            return response()->json(['message' => 'You must change your password before continuing.'], 403);
        }

        return $next($request);
    }
}
