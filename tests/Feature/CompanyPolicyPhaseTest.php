<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyPolicyVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Company\CompanyPolicyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyPolicyPhaseTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Company} */
    private function adminWithCompany(): array
    {
        Role::firstOrCreate(['name' => 'admin']);
        Permission::firstOrCreate(['name' => 'company_policies.view']);
        Permission::firstOrCreate(['name' => 'company_policies.manage']);

        $tenant = Tenant::create(['code' => 'T1', 'name' => 'Tenant 1']);
        $company = Company::create([
            'tenant_id' => $tenant->id,
            'code' => 'CT1',
            'name' => 'Công ty test',
            'policy_template_code' => 'garment',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'default_company_id' => $company->id,
        ]);
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'tok-'.uniqid()])->save();

        return [$user, $company];
    }

    public function test_overview_returns_company_policy(): void
    {
        [$user, $company] = $this->adminWithCompany();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->getJson('/api/v1/company-policies');

        $response->assertOk();
        $response->assertJsonPath('data.company.id', $company->id);
        $response->assertJsonStructure(['data' => ['domains' => ['attendance', 'payroll']]]);
    }

    public function test_hr_with_manage_can_update_payroll_domain(): void
    {
        Role::firstOrCreate(['name' => 'hr_manager']);
        Permission::firstOrCreate(['name' => 'company_policies.manage', 'guard_name' => 'web']);

        [$admin, $company] = $this->adminWithCompany();
        $hr = User::factory()->create(['tenant_id' => $company->tenant_id, 'default_company_id' => $company->id]);
        $hr->assignRole('hr_manager');
        $hr->givePermissionTo('company_policies.manage');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $hr->forceFill(['api_token' => 'hr-'.uniqid()])->save();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$hr->api_token,
            'X-Company-Id' => $company->id,
        ])->putJson('/api/v1/company-policies/domains/payroll', [
            'settings' => ['ot_coeff_weekday' => '1.6'],
            'effective_from' => '2026-06-01',
            'notes' => 'Test',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'ot_coeff_weekday',
            'value' => '1.6',
        ]);
        $this->assertDatabaseHas('company_policy_versions', [
            'company_id' => $company->id,
            'domain' => 'payroll',
        ]);
    }

    public function test_resolver_reads_company_settings(): void
    {
        [, $company] = $this->adminWithCompany();

        \App\Models\CompanySetting::create([
            'company_id' => $company->id,
            'key' => 'standard_working_days',
            'value' => '22',
        ]);

        CompanyPolicyResolver::flushCache();
        $days = CompanyPolicyResolver::for($company->id)->getString('standard_working_days');

        $this->assertSame('22', $days);
    }

    public function test_group_comparison_lists_companies(): void
    {
        [$user, $company] = $this->adminWithCompany();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->getJson('/api/v1/company-policies/group-comparison');

        $response->assertOk();
        $response->assertJsonPath('data.companies.0.company_id', $company->id);
    }

    public function test_export_and_import_policy(): void
    {
        [$user, $company] = $this->adminWithCompany();

        $export = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->getJson('/api/v1/company-policies/export');

        $export->assertOk();
        $settings = $export->json('data.settings');
        $this->assertIsArray($settings);

        $import = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/company-policies/import', [
            'settings' => array_merge($settings, ['annual_leave_standard' => '14']),
        ]);

        $import->assertOk();
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'annual_leave_standard',
            'value' => '14',
        ]);
    }
}
