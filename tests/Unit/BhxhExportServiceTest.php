<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Employee;
use App\Services\Bhxh\BhxhExportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BhxhExportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_for_increase_finds_by_hire_date(): void
    {
        $company = Company::create([
            'name' => 'Co',
            'code' => 'C1',
            'tax_code' => '0123456789',
            'social_insurance_unit_code' => 'DV001',
            'is_active' => true,
        ]);

        Employee::create([
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
            'bhxh_start_date' => '2026-05-15',
            'hire_date' => '2026-05-15',
            'is_active' => true,
        ]);

        $count = app(BhxhExportService::class)
            ->employeesForIncrease($company, Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31'))
            ->count();

        $this->assertSame(1, $count, 'Expected 1 employee for D01 in period. DB hire_date: '.Employee::first()?->hire_date);
    }
}
