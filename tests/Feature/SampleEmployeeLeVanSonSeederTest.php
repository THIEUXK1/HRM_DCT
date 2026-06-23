<?php

namespace Tests\Feature;

use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\EmploymentContract;
use Database\Seeders\HcmExtendedSeeder;
use Database\Seeders\HcmPlatformSeeder;
use Database\Seeders\InitialHrDataSeeder;
use Database\Seeders\SampleEmployeesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SampleEmployeeLeVanSonSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_emp_lvs_has_tv_and_ct_work_days_in_may_2026(): void
    {
        $this->seed(InitialHrDataSeeder::class);
        $this->seed(HcmPlatformSeeder::class);
        $this->seed(HcmExtendedSeeder::class);
        $this->seed(SampleEmployeesSeeder::class);

        $employee = Employee::where('employee_code', 'EMP-LVS')->first();
        $this->assertNotNull($employee);
        $this->assertSame('Lê Văn Sơn', $employee->full_name);
        $this->assertSame('2026-05-20', $employee->probation_end_date?->format('Y-m-d'));
        $this->assertSame('2026-05-21', $employee->official_start_date?->format('Y-m-d'));

        $summary = AttendanceSummary::where('employee_id', $employee->id)
            ->where('period', '2026-05')
            ->first();

        $this->assertNotNull($summary, 'EMP-LVS phải có bảng công tháng 2026-05.');
        $this->assertGreaterThan(0, (float) $summary->probation_work_days);
        $this->assertGreaterThan(0, (float) $summary->official_work_days);
        $this->assertSame('mixed', $summary->attendance_breakdown['meta']['employment_status'] ?? null);

        $this->assertDatabaseHas('employment_contracts', [
            'employee_id' => $employee->id,
            'contract_number' => 'CTR-LVS-PB',
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('employment_contracts', [
            'employee_id' => $employee->id,
            'contract_number' => 'CTR-LVS-CT',
            'status' => 'active',
        ]);

        $probationContract = EmploymentContract::where('contract_number', 'CTR-LVS-PB')->first();
        $this->assertSame(15_000_000, (int) $probationContract->salary_base);
        $officialContract = EmploymentContract::where('contract_number', 'CTR-LVS-CT')->first();
        $this->assertSame(15_000_000, (int) $officialContract->salary_base);

        $aprilSummary = AttendanceSummary::where('employee_id', $employee->id)
            ->where('period', '2026-04')
            ->first();
        $this->assertNotNull($aprilSummary);
        $this->assertGreaterThan(0, (float) $aprilSummary->probation_work_days);
        $this->assertGreaterThan(
            (float) $aprilSummary->official_work_days,
            (float) $aprilSummary->probation_work_days,
            'Tháng 4/2026 phải chủ yếu là công thử việc.',
        );

        $timesheet = app(\App\Services\Attendance\AttendanceTimesheetService::class)
            ->dailyTimesheet($employee->company_id, '2026-05');
        $row = collect($timesheet['employees'])->firstWhere('employee_code', 'EMP-LVS');
        $this->assertNotNull($row, 'EMP-LVS phải có trên bảng công ngày tháng 5.');
        $this->assertTrue($row['has_phase_split']);
        $this->assertGreaterThan(0, $row['totals']['probation_days']);
        $this->assertGreaterThan(0, $row['totals']['official_days']);
        $this->assertSame(2.0, (float) $row['totals']['probation_ot_hours']);
        $this->assertSame(4.0, (float) $row['totals']['official_ot_hours']);
        $this->assertSame(6.0, (float) $row['totals']['ot_hours']);

        $this->assertSame(2.0, (float) $summary->attendance_breakdown['ot_by_phase']['totals']['probation_hours']);
        $this->assertSame(4.0, (float) $summary->attendance_breakdown['ot_by_phase']['totals']['official_hours']);
        $this->assertArrayHasKey('leave_by_phase', $summary->attendance_breakdown);
    }
}
