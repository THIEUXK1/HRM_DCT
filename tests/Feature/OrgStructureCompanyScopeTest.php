<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrgStructureCompanyScopeTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithCompanies(): array
    {
        Role::firstOrCreate(['name' => 'admin']);
        $tenant = Tenant::create(['code' => 'T1', 'name' => 'T1']);

        $companyA = Company::create(['tenant_id' => $tenant->id, 'code' => 'CA', 'name' => 'Công ty A']);
        $companyB = Company::create(['tenant_id' => $tenant->id, 'code' => 'CB', 'name' => 'Công ty B']);

        $branchA = Branch::create(['company_id' => $companyA->id, 'code' => 'BRA', 'name' => 'CN A']);
        $branchB = Branch::create(['company_id' => $companyB->id, 'code' => 'BRB', 'name' => 'CN B']);

        Department::create(['branch_id' => $branchA->id, 'code' => 'HR', 'name' => 'Phòng HR A']);
        Department::create(['branch_id' => $branchB->id, 'code' => 'HR', 'name' => 'Phòng HR B']);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'default_company_id' => $companyA->id,
            'password' => Hash::make('Admin@123'),
        ]);
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'tok-org'])->save();

        return [$user, $companyA, $companyB, $branchA, $branchB];
    }

    public function test_departments_index_scoped_to_current_company(): void
    {
        [$user, $companyA] = $this->adminWithCompanies();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $companyA->id,
        ])->getJson('/api/v1/departments');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Phòng HR A');
    }

    public function test_cannot_create_department_under_branch_of_other_company(): void
    {
        [$user, $companyA, , , $branchB] = $this->adminWithCompanies();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $companyA->id,
        ])->postJson('/api/v1/departments', [
            'branch_id' => $branchB->id,
            'code' => 'IT',
            'name' => 'Phòng IT lạ',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['branch_id']);
    }

    public function test_parent_department_must_be_same_branch(): void
    {
        [$user, $companyA, , $branchA] = $this->adminWithCompanies();

        $parent = Department::create(['branch_id' => $branchA->id, 'code' => 'PARENT', 'name' => 'Phòng cha']);
        $otherBranch = Branch::create(['company_id' => $companyA->id, 'code' => 'BR2', 'name' => 'CN 2']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $companyA->id,
        ])->postJson('/api/v1/departments', [
            'branch_id' => $otherBranch->id,
            'code' => 'SUB',
            'name' => 'Bộ phận con',
            'parent_department_id' => $parent->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['parent_department_id']);
    }

    public function test_create_department_without_branch_id_uses_default_branch(): void
    {
        [$user, $companyA] = $this->adminWithCompanies();
        Branch::query()->where('company_id', $companyA->id)->delete();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $companyA->id,
        ])->postJson('/api/v1/departments', [
            'code' => 'ACC',
            'name' => 'Phòng Kế toán',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('branches', [
            'company_id' => $companyA->id,
            'code' => 'HQ',
        ]);
        $this->assertDatabaseHas('departments', [
            'code' => 'ACC',
            'name' => 'Phòng Kế toán',
        ]);
    }

    public function test_ensure_default_branch_endpoint(): void
    {
        [$user, $companyA] = $this->adminWithCompanies();
        Branch::query()->where('company_id', $companyA->id)->delete();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $companyA->id,
        ])->postJson('/api/v1/branches/ensure-default');

        $response->assertOk();
        $response->assertJsonPath('data.code', 'HQ');
        $response->assertJsonPath('data.name', 'Trụ sở chính');
    }
}
