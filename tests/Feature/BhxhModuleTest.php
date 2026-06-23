<?php

namespace Tests\Feature;

use App\Models\BhxhDeclaration;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\Bhxh\BhxhDeclarationService;
use App\Services\Bhxh\BhxhExportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BhxhModuleTest extends TestCase
{
    use RefreshDatabase;

    private const PERIOD_FROM = '2026-05-01';

    private const PERIOD_TO = '2026-05-31';

    private const HIRE_DATE = '2026-05-15';

    private function headers(): array
    {
        Role::create(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'tok'])->save();

        return ['Authorization' => 'Bearer tok'];
    }

    private function seedEmployee(Company $company): Employee
    {
        return Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'E1',
            'first_name' => 'A',
            'last_name' => 'B',
            'full_name' => 'A B',
            'email' => 'ab@test.local',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'national_id' => '001090015234',
            'insurance_salary' => 10_000_000,
            'bhxh_start_date' => self::HIRE_DATE,
            'hire_date' => self::HIRE_DATE,
            'is_active' => true,
        ]);
    }

    public function test_dashboard_and_preview_d01(): void
    {
        $headers = $this->headers();
        $company = Company::create([
            'name' => 'Co',
            'code' => 'C1',
            'tax_code' => '0123456789',
            'social_insurance_unit_code' => 'DV001',
            'is_active' => true,
        ]);

        $this->seedEmployee($company);

        $from = Carbon::parse(self::PERIOD_FROM);
        $to = Carbon::parse(self::PERIOD_TO);

        $this->assertSame(1, app(BhxhExportService::class)->employeesForIncrease($company, $from, $to)->count());

        $this->withHeaders($headers)
            ->getJson('/api/v1/bhxh/dashboard?company_id='.$company->id)
            ->assertOk()
            ->assertJsonPath('data.stats.active_employees', 1);

        $preview = $this->withHeaders($headers)
            ->getJson('/api/v1/bhxh/preview?company_id='.$company->id.'&declaration_type=d01&from='.self::PERIOD_FROM.'&to='.self::PERIOD_TO)
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $preview['total']);
        $this->assertTrue($preview['can_export']);
    }

    public function test_export_creates_declaration_record(): void
    {
        $headers = $this->headers();
        $company = Company::create([
            'name' => 'Co',
            'code' => 'C1',
            'tax_code' => '0123456789',
            'social_insurance_unit_code' => 'DV001',
            'is_active' => true,
        ]);

        $this->seedEmployee($company);

        $this->withHeaders($headers)
            ->postJson('/api/v1/bhxh/export', [
                'company_id' => $company->id,
                'declaration_type' => 'd01',
                'format' => 'csv',
                'from' => self::PERIOD_FROM,
                'to' => self::PERIOD_TO,
            ])
            ->assertOk()
            ->assertJsonPath('data.success', true);

        $this->assertEquals(1, BhxhDeclaration::count());
    }
}
