<?php

namespace App\Services\Security;

use App\Http\Controllers\Api\AuthController;
use App\Models\Company;
use App\Models\User;
use App\Models\UserCompanyRole;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UserAccessService
{
    public function __construct(private UserAuthorizationService $authz) {}

    /**
     * @param  list<int>  $companyIds
     * @param  list<string>  $roles
     */
    public function syncCompanyAccessAndRoles(
        User $actor,
        User $target,
        array $companyIds,
        array $roles,
        ?int $defaultCompanyId = null,
    ): array {
        if (! $this->authz->canManageUser($actor, $target)) {
            abort(403, 'Bạn không có quyền quản lý người dùng này.');
        }

        if (! $this->authz->canGrantCompanies($actor, $companyIds)) {
            abort(403, 'Bạn chỉ được cấp quyền các công ty mình có truy cập.');
        }

        $tenantId = CompanyContext::tenantId();
        $allowedIds = Company::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereKey($companyIds)
            ->pluck('id');

        $roles = $this->authz->filterAssignableRoles($actor, $roles);

        return DB::transaction(function () use ($target, $allowedIds, $roles, $defaultCompanyId) {
            $target->companies()->sync($allowedIds);

            UserCompanyRole::query()->where('user_id', $target->id)->delete();

            foreach ($allowedIds as $companyId) {
                foreach ($roles as $role) {
                    UserCompanyRole::create([
                        'user_id' => $target->id,
                        'company_id' => $companyId,
                        'role' => $role,
                    ]);
                }
            }

            if ($defaultCompanyId && $allowedIds->contains($defaultCompanyId)) {
                $target->update(['default_company_id' => $defaultCompanyId]);
            } elseif ($allowedIds->isNotEmpty() && ! $allowedIds->contains($target->default_company_id)) {
                $target->update(['default_company_id' => $allowedIds->first()]);
            }

            $this->flushUserCaches($target);

            return [
                'user_id' => $target->id,
                'granted_company_ids' => $allowedIds->values()->all(),
                'roles' => $roles,
                'default_company_id' => $target->fresh()->default_company_id,
            ];
        });
    }

    /**
     * @param  list<string>  $roles
     */
    public function syncRolesForCompany(User $actor, User $target, int $companyId, array $roles): array
    {
        if (! $this->authz->canManageUser($actor, $target)) {
            abort(403, 'Bạn không có quyền quản lý người dùng này.');
        }

        if (! $this->authz->canGrantCompanies($actor, [$companyId])) {
            abort(403, 'Bạn không có quyền trên công ty này.');
        }

        if (! $target->companies()->whereKey($companyId)->exists()
            && (int) $target->default_company_id !== $companyId
            && (int) ($target->employee?->company_id) !== $companyId) {
            abort(422, 'Người dùng chưa được cấp truy cập công ty này.');
        }

        $roles = $this->authz->filterAssignableRoles($actor, $roles);

        UserCompanyRole::query()
            ->where('user_id', $target->id)
            ->where('company_id', $companyId)
            ->delete();

        foreach ($roles as $role) {
            UserCompanyRole::create([
                'user_id' => $target->id,
                'company_id' => $companyId,
                'role' => $role,
            ]);
        }

        $this->flushUserCaches($target);

        return [
            'user_id' => $target->id,
            'company_id' => $companyId,
            'roles' => $roles,
        ];
    }

    public function flushUserCaches(User $user): void
    {
        AuthController::bustUserCache($user->id);
        foreach ($this->authz->accessibleCompanyIds($user) as $companyId) {
            Cache::forget("user_company_access:{$user->id}:{$companyId}");
        }
    }
}
