<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserCompanyRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserCompanyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function seedTenantWithTwoCompanies(): array
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $tenant = Tenant::create(['code' => 'T1', 'name' => 'Tập đoàn']);
        $companyA = Company::create(['tenant_id' => $tenant->id, 'code' => 'CA', 'name' => 'Công ty A']);
        $companyB = Company::create(['tenant_id' => $tenant->id, 'code' => 'CB', 'name' => 'Công ty B']);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'default_company_id' => $companyA->id,
            'password' => Hash::make('Admin@123'),
        ]);
        $admin->assignRole('admin');
        $admin->forceFill(['api_token' => 'tok-admin'])->save();

        return [$tenant, $companyA, $companyB, $admin];
    }

    public function test_sync_access_grants_multi_company_roles(): void
    {
        [, $companyA, $companyB, $admin] = $this->seedTenantWithTwoCompanies();

        $target = User::factory()->create([
            'tenant_id' => $companyA->tenant_id,
            'email' => 'hr@test.local',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $companyA->id,
        ])->putJson("/api/v1/users/{$target->id}/access", [
            'company_ids' => [$companyA->id, $companyB->id],
            'roles' => ['hr_manager'],
            'default_company_id' => $companyA->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('user_companies', [
            'user_id' => $target->id,
            'company_id' => $companyB->id,
        ]);
        $this->assertDatabaseHas('user_company_roles', [
            'user_id' => $target->id,
            'company_id' => $companyA->id,
            'role' => 'hr_manager',
        ]);
    }

    public function test_company_scoped_permissions_differ_by_header(): void
    {
        [, $companyA, $companyB, $admin] = $this->seedTenantWithTwoCompanies();

        $target = User::factory()->create([
            'tenant_id' => $companyA->tenant_id,
            'default_company_id' => $companyA->id,
        ]);
        $target->companies()->sync([$companyA->id, $companyB->id]);
        UserCompanyRole::create(['user_id' => $target->id, 'company_id' => $companyA->id, 'role' => 'hr_manager']);
        UserCompanyRole::create(['user_id' => $target->id, 'company_id' => $companyB->id, 'role' => 'employee']);
        $target->forceFill(['api_token' => 'tok-target'])->save();

        $meA = $this->withHeaders([
            'Authorization' => 'Bearer tok-target',
            'X-Company-Id' => $companyA->id,
        ])->getJson('/api/v1/auth/me');

        $meA->assertOk();
        $meA->assertJsonPath('data.roles.0', 'hr_manager');
        $this->assertContains('payroll.view', $meA->json('data.permissions'));

        $meB = $this->withHeaders([
            'Authorization' => 'Bearer tok-target',
            'X-Company-Id' => $companyB->id,
        ])->getJson('/api/v1/auth/me');

        $meB->assertOk();
        $meB->assertJsonPath('data.roles.0', 'employee');
        $this->assertNotContains('payroll.view', $meB->json('data.permissions'));
    }

    public function test_hr_manager_cannot_assign_admin_role(): void
    {
        [, $companyA, , $admin] = $this->seedTenantWithTwoCompanies();

        $hr = User::factory()->create([
            'tenant_id' => $companyA->tenant_id,
            'default_company_id' => $companyA->id,
        ]);
        $hr->assignRole('hr_manager');
        $hr->companies()->sync([$companyA->id]);
        UserCompanyRole::create(['user_id' => $hr->id, 'company_id' => $companyA->id, 'role' => 'hr_manager']);
        $hr->forceFill(['api_token' => 'tok-hr'])->save();

        $target = User::factory()->create(['tenant_id' => $companyA->tenant_id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer tok-hr',
            'X-Company-Id' => $companyA->id,
        ])->putJson("/api/v1/users/{$target->id}/access", [
            'company_ids' => [$companyA->id],
            'roles' => ['admin'],
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('user_company_roles', [
            'user_id' => $target->id,
            'role' => 'admin',
        ]);
    }
}
