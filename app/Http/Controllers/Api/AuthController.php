<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Security\UserAuthorizationService;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends ApiController
{
    /** Token TTL in hours. Can be overridden by config('auth.token_ttl_hours'). */
    private function tokenTtlHours(): int
    {
        return (int) config('auth.token_ttl_hours', 8);
    }

    public function login(LoginRequest $request): \Illuminate\Http\JsonResponse
    {
        $login = $request->loginIdentifier();
        $user = $this->resolveUserByLogin($login);

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            AuditLogger::loginFailed($login);
            return $this->error('Thông tin đăng nhập không chính xác.', 401);
        }

        Auth::login($user);
        $user->api_token = Str::random(80);
        $user->token_expires_at = now()->addHours($this->tokenTtlHours());
        $user->save();

        AuditLogger::login($user);

        return $this->success([
            'token' => $user->api_token,
            'expires_at' => $user->token_expires_at->toIso8601String(),
            'ttl_hours' => $this->tokenTtlHours(),
            'must_change_password' => (bool) $user->must_change_password,
            'user' => $this->userPayload($user),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return $this->error('Mật khẩu hiện tại không đúng.', 422);
        }

        $user->password = $request->input('password');
        $user->must_change_password = false;
        $user->password_changed_at = now();
        $user->save();

        self::bustUserCache($user->id);

        return $this->success([
            'message' => 'Đã đổi mật khẩu thành công.',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function logout(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        if ($user) {
            $user->api_token = null;
            $user->token_expires_at = null;
            $user->save();
        }

        Auth::logout();
        return $this->noContent();
    }

    public function me(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        return $this->success(array_merge($this->userPayload($user), [
            'token_expires_at' => $user->token_expires_at?->toIso8601String(),
        ]));
    }

    /** Rotate token — renews the expiry window. */
    public function rotate(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        $user->api_token = Str::random(80);
        $user->token_expires_at = now()->addHours($this->tokenTtlHours());
        $user->save();

        return $this->success([
            'token' => $user->api_token,
            'expires_at' => $user->token_expires_at->toIso8601String(),
        ]);
    }

    protected function userPayload($user): array
    {
        $companyId = CompanyContext::id()
            ?? (request()->header('X-Company-Id') ? (int) request()->header('X-Company-Id') : null)
            ?? $user->default_company_id;
        $cacheKey = "user_payload:{$user->id}:".($companyId ?? '_');
        $authz = app(UserAuthorizationService::class);

        $cached = Cache::remember($cacheKey, 600, function () use ($user, $companyId, $authz) {
            $user->load(['employee:id,employee_code,full_name,company_id']);

            return [
                'roles' => $authz->rolesForCompany($user, $companyId ? (int) $companyId : null),
                'permissions' => $authz->permissionsForCompany($user, $companyId ? (int) $companyId : null),
                'employee_code' => $user->employee?->employee_code,
                'login' => $user->employee?->employee_code,
                'accessible_company_ids' => $authz->accessibleCompanyIds($user)->values()->all(),
            ];
        });

        return array_merge(
            $user->only([
                'id', 'name', 'email', 'employee_id',
                'default_company_id', 'tenant_id', 'token_expires_at',
                'must_change_password', 'password_changed_at',
            ]),
            $cached,
        );
    }

    /** Invalidate cached payload when roles/permissions change. */
    public static function bustUserCache(int $userId): void
    {
        Cache::forget("user_payload:{$userId}:_");
        $user = User::find($userId);
        if (! $user) {
            return;
        }
        $authz = app(UserAuthorizationService::class);
        foreach ($authz->accessibleCompanyIds($user) as $companyId) {
            Cache::forget("user_payload:{$userId}:{$companyId}");
        }
    }

    private function resolveUserByLogin(string $login): ?User
    {
        $login = trim($login);

        if (str_contains($login, '@')) {
            return User::where('email', $login)->first();
        }

        $employeeCode = strtoupper($login);

        return User::query()
            ->whereHas('employee', fn ($q) => $q->whereRaw('UPPER(employee_code) = ?', [$employeeCode]))
            ->first();
    }
}
