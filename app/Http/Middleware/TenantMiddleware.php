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
        // Coba ambil token dari Authorization header dulu
        $token = $request->bearerToken();

        // Kalau tidak ada (SSE/EventSource), ambil dari query param
        if (!$token) {
            $token = $request->query('token');
        }

        if (!$token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $payload  = JWTAuth::setToken($token)->getPayload();
            $tenantId = $payload->get('tenant_id');
            $userId   = $payload->get('sub');

            $request->merge(['_tenant_id' => $tenantId, '_user_id' => $userId]);

            return $next($request);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }
    }
}
