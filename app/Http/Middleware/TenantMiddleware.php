<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (JWTException $e) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$user || !$user->tenant_id) {
            return response()->json(['message' => 'Tenant not found.'], 403);
        }

        $request->merge(['_tenant_id' => $user->tenant_id]);

        return $next($request);
    }
}
