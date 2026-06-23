<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PayrollCycle;
use App\Models\PayrollResult;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollCycleRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function headers(Company $company): array
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['api_token' => 'pay-rev-'.uniqid()]);
        $user->assignRole('admin');
        CompanyContext::set($company->id);

        return [
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => (string) $company->id,
        ];
    }

    public function test_can_create_second_run_after_locked_cycle(): void
    {
        $tenant = Tenant::create(['code' => 'T1', 'name' => 'T1']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C1', 'name' => 'C1']);
        $headers = $this->headers($company);

        $locked = PayrollCycle::create([
            'company_id' => $company->id,
            'period' => '2026-04',
            'run_number' => 1,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'status' => 'locked',
            'locked_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/payroll-cycles', [
            'period' => '2026-04',
            'revision_note' => 'Điều chỉnh công',
        ], $headers);

        $response->assertCreated()
            ->assertJsonPath('data.run_number', 2)
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('payroll_cycles', [
            'id' => $locked->id,
            'status' => 'locked',
        ]);
    }

    public function test_cannot_create_when_draft_cycle_exists(): void
    {
        $tenant = Tenant::create(['code' => 'T2', 'name' => 'T2']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C2', 'name' => 'C2']);
        $headers = $this->headers($company);

        PayrollCycle::create([
            'company_id' => $company->id,
            'period' => '2026-05',
            'run_number' => 1,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'status' => 'calculated',
        ]);

        $this->postJson('/api/v1/payroll-cycles', ['period' => '2026-05'], $headers)
            ->assertStatus(422);
    }

    public function test_calculate_rejects_locked_cycle(): void
    {
        $tenant = Tenant::create(['code' => 'T3', 'name' => 'T3']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C3', 'name' => 'C3']);
        $headers = $this->headers($company);

        $cycle = PayrollCycle::create([
            'company_id' => $company->id,
            'period' => '2026-03',
            'run_number' => 1,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'status' => 'locked',
        ]);

        PayrollResult::create([
            'payroll_cycle_id' => $cycle->id,
            'employee_id' => \App\Models\Employee::create([
                'company_id' => $company->id,
                'employee_code' => 'E1',
                'first_name' => 'A',
                'last_name' => 'B',
                'full_name' => 'B A',
                'email' => 'e1@test.local',
            ])->id,
            'gross_salary' => 1,
            'net_salary' => 1,
        ]);

        $this->postJson("/api/v1/payroll-cycles/{$cycle->id}/calculate", [], $headers)
            ->assertStatus(500);
    }
}
