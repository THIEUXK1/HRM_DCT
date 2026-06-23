<?php



namespace App\Http\Controllers\Api;



use App\Models\Company;

use App\Models\User;

use App\Services\AuditLogger;

use App\Services\Security\UserAccessService;

use App\Services\Security\UserAuthorizationService;

use App\Support\CompanyContext;

use App\Support\QuerySearch;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Cache;

use Spatie\Permission\Models\Role;



class UserController extends ApiController

{

    public function __construct(

        private UserAuthorizationService $authz,

        private UserAccessService $access,

    ) {}



    /**

     * Danh sách user trong tenant (HR/admin quản lý phân quyền).

     */

    public function index(Request $request): JsonResponse

    {

        $actor = auth()->user();

        abort_unless($actor && ($actor->isTenantAdmin() || $actor->can('users.manage')), 403);



        $query = User::query()

            ->with(['roles', 'companies:id,name,code', 'employee:id,full_name,employee_code,company_id'])

            ->select(['id', 'name', 'email', 'tenant_id', 'default_company_id', 'employee_id'])

            ->orderBy('name');



        $tenantId = CompanyContext::tenantId();

        if ($tenantId) {

            $query->where('tenant_id', $tenantId);

        }



        if (! $actor->isTenantAdmin()) {

            $companyIds = $this->authz->accessibleCompanyIds($actor);

            $query->where(function ($q) use ($companyIds) {

                $q->whereIn('default_company_id', $companyIds)

                    ->orWhereHas('companies', fn ($c) => $c->whereIn('companies.id', $companyIds))

                    ->orWhereHas('employee', fn ($e) => $e->whereIn('company_id', $companyIds));

            });

        }



        QuerySearch::user($query, $request->get('search'));



        $users = $query->paginate($request->integer('per_page', 100));



        $users->getCollection()->transform(function (User $user) {

            return array_merge($user->toArray(), [

                'company_roles' => $this->authz->companyRolesMap($user),

            ]);

        });



        return $this->success($users);

    }



    public function syncRoles(Request $request, User $user): JsonResponse

    {

        $actor = auth()->user();

        abort_unless($actor && ($actor->isTenantAdmin() || $actor->can('users.manage')), 403);



        $data = $request->validate([

            'roles' => ['required', 'array'],

            'roles.*' => ['string', 'exists:roles,name'],

            'company_id' => ['nullable', 'integer', 'exists:companies,id'],

        ]);



        $companyId = (int) ($data['company_id'] ?? CompanyContext::id() ?? $user->default_company_id);



        if ($companyId) {

            $result = $this->access->syncRolesForCompany($actor, $user, $companyId, $data['roles']);

            AuditLogger::roleAssigned($user, $data['roles']);



            return $this->success($result);

        }



        abort_unless($actor->isTenantAdmin(), 403, 'Chỉ admin tập đoàn mới gán vai trò toàn hệ thống.');

        $roles = $this->authz->filterAssignableRoles($actor, $data['roles']);

        $user->syncRoles($roles);

        AuditLogger::roleAssigned($user, $roles);

        $this->access->flushUserCaches($user);



        return $this->success($user->load('roles'));

    }



    /**

     * Cấp truy cập + vai trò theo công ty (một hoặc nhiều CTTV).

     * Body: { company_ids: [], roles: [], default_company_id?: int }

     */

    public function syncAccess(Request $request, User $user): JsonResponse

    {

        $actor = auth()->user();

        abort_unless($actor && ($actor->isTenantAdmin() || $actor->can('users.manage')), 403);



        $data = $request->validate([

            'company_ids' => ['required', 'array', 'min:1'],

            'company_ids.*' => ['integer', 'exists:companies,id'],

            'roles' => ['required', 'array', 'min:1'],

            'roles.*' => ['string', 'exists:roles,name'],

            'default_company_id' => ['nullable', 'integer', 'exists:companies,id'],

        ]);



        $result = $this->access->syncCompanyAccessAndRoles(

            $actor,

            $user,

            $data['company_ids'],

            $data['roles'],

            $data['default_company_id'] ?? null,

        );



        AuditLogger::companyAccessChanged($user, $result['granted_company_ids']);

        AuditLogger::roleAssigned($user, $result['roles']);



        return $this->success($result);

    }



    /**

     * @deprecated dùng syncAccess — giữ tương thích API cũ

     */

    public function syncCompanyAccess(Request $request, User $user): JsonResponse

    {

        $actor = auth()->user();

        abort_unless($actor && ($actor->isTenantAdmin() || $actor->can('users.manage')), 403);



        $data = $request->validate([

            'company_ids' => ['required', 'array'],

            'company_ids.*' => ['integer', 'exists:companies,id'],

        ]);



        if (! $this->authz->canManageUser($actor, $user) || ! $this->authz->canGrantCompanies($actor, $data['company_ids'])) {

            abort(403);

        }



        $tenantId = CompanyContext::tenantId();

        $allowedIds = Company::query()

            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))

            ->whereKey($data['company_ids'])

            ->pluck('id');



        $user->companies()->sync($allowedIds);

        AuditLogger::companyAccessChanged($user, $allowedIds->toArray());

        $this->access->flushUserCaches($user);



        return $this->success([

            'user_id' => $user->id,

            'granted_company_ids' => $allowedIds->values(),

        ]);

    }



    public function companyAccess(User $user): JsonResponse

    {

        $actor = auth()->user();

        abort_unless($actor && ($actor->isTenantAdmin() || $actor->can('users.manage')), 403);

        abort_unless($this->authz->canManageUser($actor, $user), 403);



        $ids = $this->authz->accessibleCompanyIds($user);

        $companies = Company::query()->whereKey($ids)->get(['id', 'name', 'code', 'tenant_id']);



        return $this->success([

            'companies' => $companies,

            'company_roles' => $this->authz->companyRolesMap($user),

            'default_company_id' => $user->default_company_id,

            'global_roles' => $user->getRoleNames(),

        ]);

    }



    public function assignableRoles(): JsonResponse

    {

        $actor = auth()->user();

        abort_unless($actor && ($actor->isTenantAdmin() || $actor->can('users.manage')), 403);



        $roles = Role::query()->orderBy('name')->pluck('name');

        if (! $actor->isTenantAdmin()) {

            $roles = $roles->reject(fn ($name) => $name === 'admin')->values();

        }



        return $this->success($roles);

    }

}


