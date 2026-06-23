<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase M1: Kiểm tra cách ly dữ liệu đa tenant / đa công ty.
 */
class MultiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeTenant(string $code): Tenant
    {
        return Tenant::create(['name' => "Tenant $code", 'code' => $code, 'is_active' => true]);
    }

    private function makeCompany(Tenant $tenant, string $code): Company
    {
        return Company::create(['tenant_id' => $tenant->id, 'name' => "Company $code", 'code' => $code]);
    }

    private function makeBranch(Company $company, string $code): Branch
    {
        return Branch::create(['company_id' => $company->id, 'name' => "Branch $code", 'code' => $code]);
    }

    private function makeDept(Branch $branch, string $code): Department
    {
        return Department::create(['branch_id' => $branch->id, 'name' => "Dept $code", 'code' => $code]);
    }

    private function makeEmployee(Company $company, Branch $branch, Department $dept, string $code): Employee
    {
        return Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $dept->id,
            'first_name' => $code,
            'last_name' => 'Test',
            'full_name' => "$code Test",
            'employee_code' => $code,
            'email' => strtolower($code).'@test.local',
            'is_active' => true,
        ]);
    }

    private function makeUser(Tenant $tenant, Employee $employee, string $token, string $role = 'hr_manager'): User
    {
        // RolePermissionSeeder already ran; just ensure the role exists.
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'default_company_id' => $employee->company_id,
            'email' => $employee->email,
        ]);
        $user->assignRole($role);
        $user->forceFill(['api_token' => $token])->save();

        return $user;
    }

    // -------------------------------------------------------------------------
    // Test 1: Người dùng không thể xem công ty của tenant khác
    // -------------------------------------------------------------------------

    public function test_user_cannot_see_companies_of_another_tenant(): void
    {
        $tenantA = $this->makeTenant('TENANT-A');
        $tenantB = $this->makeTenant('TENANT-B');

        $compA = $this->makeCompany($tenantA, 'COMP-A');
        $compB = $this->makeCompany($tenantB, 'COMP-B');

        $branchA = $this->makeBranch($compA, 'BR-A');
        $deptA   = $this->makeDept($branchA, 'DEPT-A');
        $empA    = $this->makeEmployee($compA, $branchA, $deptA, 'EMP-A');
        $userA   = $this->makeUser($tenantA, $empA, 'token-user-a');

        $response = $this->withHeaders(['Authorization' => 'Bearer token-user-a'])
            ->getJson('/api/v1/companies');

        $response->assertOk();
        $companyIds = collect($response->json('data'))->pluck('id');

        $this->assertContains($compA->id, $companyIds->toArray());
        $this->assertNotContains($compB->id, $companyIds->toArray());
    }

    // -------------------------------------------------------------------------
    // Test 2: Middleware cho phép truy cập khi employee thuộc công ty đó
    // -------------------------------------------------------------------------

    public function test_employee_linked_user_can_access_their_company_via_header(): void
    {
        $tenant  = $this->makeTenant('TENANT-C');
        $company = $this->makeCompany($tenant, 'COMP-C');
        $branch  = $this->makeBranch($company, 'BR-C');
        $dept    = $this->makeDept($branch, 'DEPT-C');
        $emp     = $this->makeEmployee($company, $branch, $dept, 'EMP-C1');

        // User KHÔNG có default_company_id nhưng employee thuộc company
        Role::firstOrCreate(['name' => 'hr_manager', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'employee_id' => $emp->id,
            'default_company_id' => null,   // <-- chưa set
        ]);
        $user->assignRole('hr_manager');
        $user->forceFill(['api_token' => 'token-emp-company'])->save();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer token-emp-company',
            'X-Company-Id'  => $company->id,
        ])->getJson('/api/v1/employees');

        // Phải 200 nhờ fallback check employee→company
        $response->assertOk();
    }

    // -------------------------------------------------------------------------
    // Test 3: Middleware từ chối cross-company
    // -------------------------------------------------------------------------

    public function test_user_is_blocked_from_accessing_another_companys_data(): void
    {
        $tenant   = $this->makeTenant('TENANT-D');
        $companyA = $this->makeCompany($tenant, 'COMP-D1');
        $companyB = $this->makeCompany($tenant, 'COMP-D2');

        $branchA = $this->makeBranch($companyA, 'BR-D1');
        $deptA   = $this->makeDept($branchA, 'DEPT-D1');
        $empA    = $this->makeEmployee($companyA, $branchA, $deptA, 'EMP-D1');

        Role::firstOrCreate(['name' => 'hr_manager', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'employee_id' => $empA->id,
            'default_company_id' => $companyA->id,
        ]);
        $user->assignRole('hr_manager');
        $user->forceFill(['api_token' => 'token-cross'])->save();

        // Cố tình request với Company B mà user không thuộc
        $response = $this->withHeaders([
            'Authorization' => 'Bearer token-cross',
            'X-Company-Id'  => $companyB->id,
        ])->getJson('/api/v1/employees');

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Test 4: Admin có thể xem tất cả công ty trong tenant
    // -------------------------------------------------------------------------

    public function test_admin_can_see_all_companies_in_tenant(): void
    {
        $tenant  = $this->makeTenant('TENANT-E');
        $compA   = $this->makeCompany($tenant, 'COMP-E1');
        $compB   = $this->makeCompany($tenant, 'COMP-E2');

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'default_company_id' => $compA->id,
        ]);
        $admin->assignRole('admin');
        $admin->forceFill(['api_token' => 'token-admin-e'])->save();

        $response = $this->withHeaders(['Authorization' => 'Bearer token-admin-e'])
            ->getJson('/api/v1/companies');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($compA->id, $ids->toArray());
        $this->assertContains($compB->id, $ids->toArray());
    }

    // -------------------------------------------------------------------------
    // Test 5: syncCompanyAccess — cấp / thu hồi quyền công ty
    // -------------------------------------------------------------------------

    public function test_admin_can_grant_and_revoke_company_access_for_user(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $tenant   = $this->makeTenant('TENANT-F');
        $companyA = $this->makeCompany($tenant, 'COMP-F1');
        $companyB = $this->makeCompany($tenant, 'COMP-F2');

        $branchA = $this->makeBranch($companyA, 'BR-F1');
        $deptA   = $this->makeDept($branchA, 'DEPT-F1');
        $empA    = $this->makeEmployee($companyA, $branchA, $deptA, 'EMP-F1');

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'default_company_id' => $companyA->id,
        ]);
        $admin->assignRole('admin');
        $admin->forceFill(['api_token' => 'token-admin-f'])->save();

        $targetUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'employee_id' => $empA->id,
            'default_company_id' => $companyA->id,
        ]);

        // Cấp Company B cho targetUser
        $response = $this->withHeaders([
            'Authorization' => 'Bearer token-admin-f',
            'X-Company-Id'  => $companyA->id,
        ])->putJson("/api/v1/users/{$targetUser->id}/company-access", [
            'company_ids' => [$companyA->id, $companyB->id],
        ]);

        $response->assertOk();
        $this->assertCount(2, $response->json('data.granted_company_ids'));
        $this->assertTrue(
            $targetUser->fresh()->companies()->whereKey($companyB->id)->exists()
        );

        // Thu hồi Company B
        $this->withHeaders([
            'Authorization' => 'Bearer token-admin-f',
            'X-Company-Id'  => $companyA->id,
        ])->putJson("/api/v1/users/{$targetUser->id}/company-access", [
            'company_ids' => [$companyA->id],
        ])->assertOk();

        $this->assertFalse(
            $targetUser->fresh()->companies()->whereKey($companyB->id)->exists()
        );
    }
}
