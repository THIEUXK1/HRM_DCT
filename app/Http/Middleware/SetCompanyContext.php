<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SetCompanyContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user?->tenant_id) {
            CompanyContext::setTenant((int) $user->tenant_id);
        }

        $companyId = (int) (
            $request->header('X-Company-Id')
            ?? $request->query('company_id')
            ?? $user?->default_company_id
            ?? 0
        );

        if ($companyId) {
            // Cache company row for 5 min — company data rarely changes per request
            $company = Cache::remember("company:{$companyId}", 300, function () use ($companyId) {
                return Company::query()->find($companyId);
            });

            if (! $company) {
                return response()->json(['message' => 'Công ty không tồn tại'], 422);
            }

            if ($user?->tenant_id && (int) $user->tenant_id !== (int) $company->tenant_id) {
                return response()->json(['message' => 'Công ty không thuộc tenant hiện tại'], 403);
            }

            // Admin bypasses access check entirely
            if ($user && ! $user->hasRole('admin')) {
                $hasAccess = $this->userCanAccessCompany($user, $company);
                if (! $hasAccess) {
                    return response()->json(['message' => 'Bạn không có quyền truy cập công ty này'], 403);
                }
            }

            CompanyContext::setFromCompany((int) $company->id);
        }

        return $next($request);
    }

    private function userCanAccessCompany($user, $company): bool
    {
        // 1. Default company — no extra query needed
        if ((int) ($user->default_company_id ?? 0) === (int) $company->id) {
            return true;
        }

        // 2. Explicit pivot grant — cache per user+company pair
        $pivotKey = "user_company_access:{$user->id}:{$company->id}";
        return Cache::remember($pivotKey, 300, function () use ($user, $company) {
            if ($user->companies()->whereKey($company->id)->exists()) {
                return true;
            }
            // 3. Employee's company
            if ($user->employee_id) {
                return \App\Models\Employee::query()
                    ->where('id', $user->employee_id)
                    ->where('company_id', $company->id)
                    ->exists();
            }
            return false;
        });
    }
}
