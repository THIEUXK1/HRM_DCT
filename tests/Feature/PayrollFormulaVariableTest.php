<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PayrollFormulaCustomVariable;
use App\Models\User;
use App\Services\Payroll\PayrollFormulaVariableService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollFormulaVariableTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(Company $company): array
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['api_token' => 'formula-var-'.uniqid()]);
        $user->assignRole('admin');
        CompanyContext::set($company->id);

        return [
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => (string) $company->id,
        ];
    }

    public function test_admin_can_list_and_update_formula_parameters(): void
    {
        $company = Company::create(['name' => 'Công ty test', 'code' => 'T'.uniqid()]);
        $headers = $this->adminHeaders($company);

        $response = $this->getJson('/api/v1/payroll-formula-variables', $headers);

        $response->assertOk()
            ->assertJsonPath('data.parameters.0.key', 'performance_bonus_enabled');

        $update = $this->putJson('/api/v1/payroll-formula-variables/parameters', [
                'parameters' => [
                    'performance_bonus_enabled' => '1',
                    'performance_bonus_rate' => '0.2',
                    'termination_unused_leave_days_default' => '3',
                    'sales_commission_enabled' => '0',
                    'sales_commission_rate' => '0.05',
                ],
            ], $headers);

        $update->assertOk();

        $rateParam = collect($update->json('data.parameters'))
            ->firstWhere('key', 'performance_bonus_rate');
        $this->assertSame('0.2', $rateParam['value'] ?? null);
    }

    public function test_custom_variable_merges_into_formula_context(): void
    {
        $company = Company::create(['name' => 'Công ty test', 'code' => 'T'.uniqid()]);

        PayrollFormulaCustomVariable::create([
            'company_id' => $company->id,
            'code' => 'meal_allowance',
            'label' => 'Phụ cấp ăn',
            'value' => 500000,
            'is_active' => true,
        ]);

        $service = app(PayrollFormulaVariableService::class);
        $context = $service->enrichContext(['base_pay_total' => 10_000_000], $company->id);

        $this->assertEquals(500000.0, $context['meal_allowance']);
    }

    public function test_rejects_reserved_custom_variable_code(): void
    {
        $company = Company::create(['name' => 'Công ty test', 'code' => 'T'.uniqid()]);
        $service = app(PayrollFormulaVariableService::class);

        $this->expectException(\InvalidArgumentException::class);

        $service->createCustomVariable($company->id, [
            'code' => 'ot_pay',
            'label' => 'OT',
            'value' => 1,
        ]);
    }
}
