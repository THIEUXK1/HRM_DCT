<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Models\User;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $this->resolveToken($request);

        if (! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Cache user lookup by token for 5 minutes to avoid per-request DB hit
        $cacheKey = 'api_token:' . hash('sha256', $token);
        $userId   = Cache::remember($cacheKey, 300, function () use ($token) {
            return User::where('api_token', $token)->value('id');
        });

        if (! $userId) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = User::find($userId);

        if (! $user || $user->api_token !== $token) {
            Cache::forget($cacheKey);
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->isTokenExpired()) {
            Cache::forget($cacheKey);
            $user->update(['api_token' => null, 'token_expires_at' => null]);
            return response()->json([
                'message' => 'Token đã hết hạn. Vui lòng đăng nhập lại.',
                'code'    => 'TOKEN_EXPIRED',
            ], 401);
        }

        Auth::login($user);

        return $next($request);
    }

    protected function resolveToken(Request $request): ?string
    {
        $token = $request->bearerToken();

        if (!$token) {
            $token = $request->header('X-API-TOKEN') ?: $request->query('api_token');
        }

        return $token;
    }
}
