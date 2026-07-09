<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\JwtService;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    protected JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'message' => 'Unauthorized - Access token missing'
            ], 401);
        }

        $payload = $this->jwtService->decodeToken($token);

        if (!$payload || !isset($payload->sub)) {
            return response()->json([
                'message' => 'Unauthorized - Invalid or expired token'
            ], 401);
        }

        // Verify type is access token, not refresh token
        if (isset($payload->type) && $payload->type === 'refresh') {
            return response()->json([
                'message' => 'Unauthorized - Invalid token type'
            ], 401);
        }

        $user = User::find($payload->sub);

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized - User does not exist'
            ], 401);
        }

        // Log the user in for the current request
        auth()->setUser($user);

        return $next($request);
    }
}
