<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkScheduleGroup;
use App\Services\Company\CompanyPolicyTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyPolicyTemplateTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Company} */
    private function adminWithCompany(): array
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'hr_manager']);

        $tenant = Tenant::create(['code' => 'T1', 'name' => 'Tenant 1']);
        $company = Company::create([
            'tenant_id' => $tenant->id,
            'code' => 'CT1',
            'name' => 'Công ty test',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('Admin@123'),
            'default_company_id' => $company->id,
        ]);
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'admin-'.uniqid()])->save();

        return [$user, $company];
    }

    public function test_lists_policy_templates(): void
    {
        [$user, $company] = $this->adminWithCompany();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->getJson('/api/v1/policy-templates');

        $response->assertOk();
        $response->assertJsonFragment(['code' => 'textile']);
        $response->assertJsonFragment(['code' => 'garment']);
        $response->assertJsonFragment(['code' => 'trading']);
    }

    public function test_admin_can_apply_trading_template(): void
    {
        [$user, $company] = $this->adminWithCompany();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson("/api/v1/companies/{$company->id}/apply-policy-template", [
            'template_code' => 'trading',
            'overwrite' => true,
        ]);

        $response->assertOk();
        $company->refresh();
        $this->assertSame('trading', $company->policy_template_code);
        $this->assertSame('trading', $company->industry_code);
        $this->assertNotNull($company->policy_applied_at);

        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'standard_working_days',
            'value' => '22',
        ]);

        $this->assertDatabaseHas('payroll_formula_rules', [
            'company_id' => $company->id,
            'code' => 'SALES_COMMISSION',
            'is_active' => true,
        ]);
    }

    public function test_textile_template_seeds_production_work_schedule(): void
    {
        [$user, $company] = $this->adminWithCompany();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson("/api/v1/companies/{$company->id}/apply-policy-template", [
            'template_code' => 'textile',
            'overwrite' => true,
        ])->assertOk();

        $this->assertTrue(
            WorkScheduleGroup::where('company_id', $company->id)
                ->where('group_type', 'production')
                ->exists()
        );
    }

    public function test_hr_manager_cannot_apply_policy_template(): void
    {
        [$admin, $company] = $this->adminWithCompany();

        $hr = User::factory()->create([
            'tenant_id' => $company->tenant_id,
            'default_company_id' => $company->id,
        ]);
        $hr->assignRole('hr_manager');
        $hr->forceFill(['api_token' => 'hr-'.uniqid()])->save();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$hr->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson("/api/v1/companies/{$company->id}/apply-policy-template", [
            'template_code' => 'garment',
        ])->assertForbidden();
    }

    public function test_create_company_with_template_applies_policy(): void
    {
        [$user, $company] = $this->adminWithCompany();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/companies', [
            'code' => 'KD01',
            'name' => 'Công ty KD',
            'policy_template_code' => 'trading',
        ]);

        $response->assertCreated();
        $newId = $response->json('data.id');
        $this->assertDatabaseHas('companies', [
            'id' => $newId,
            'policy_template_code' => 'trading',
        ]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $newId,
            'key' => 'sales_commission_enabled',
            'value' => '1',
        ]);
    }

    public function test_migrate_existing_companies_sets_default_template(): void
    {
        $tenant = Tenant::create(['code' => 'T2', 'name' => 'T2']);
        $fresh = Company::create([
            'tenant_id' => $tenant->id,
            'code' => 'NEW',
            'name' => 'Fresh Co',
            'policy_template_code' => null,
        ]);

        app(CompanyPolicyTemplateService::class)->migrateExistingCompanies('garment');

        $fresh->refresh();
        $this->assertSame('garment', $fresh->policy_template_code);
    }
}
