<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Tenant;
use App\Models\WorkShift;
use App\Services\Attendance\AttendanceSummaryService;
use App\Services\Payroll\PayrollEarningsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeavePhaseSplitAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function seedEmployeeWithPhaseSplit(): array
    {
        $tenant = Tenant::create(['code' => 'T-LPS', 'name' => 'T-LPS']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C-LPS', 'name' => 'C-LPS']);

        WorkShift::create([
            'company_id' => $company->id,
            'code' => 'CA1',
            'name' => 'Ca 1',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_minutes' => 60,
            'is_active' => true,
        ]);

        foreach (config('hr_vn.leave_types', []) as $def) {
            LeaveType::create(array_merge($def, ['company_id' => $company->id]));
        }

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV-LPS',
            'first_name' => 'Leave',
            'last_name' => 'Phase',
            'full_name' => 'Leave Phase',
            'email' => 'leave_phase@test.local',
            'hire_date' => '2026-05-01',
            'probation_end_date' => '2026-05-15',
            'official_start_date' => '2026-05-16',
            'is_active' => true,
        ]);

        EmploymentContract::create([
            'employee_id' => $employee->id,
            'contract_number' => 'HD-LPS-'.uniqid(),
            'contract_type' => 'fixed_term',
            'status' => 'active',
            'start_date' => '2026-05-01',
            'salary_base' => 20_000_000,
            'probation_salary' => 17_000_000,
            'probation_months' => 2,
        ]);

        foreach (['2026-05-05', '2026-05-06', '2026-05-07', '2026-05-19', '2026-05-20', '2026-05-21'] as $date) {
            AttendanceLog::create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'work_date' => $date,
                'check_in_at' => "{$date} 08:00:00",
                'check_out_at' => "{$date} 17:00:00",
                'source' => 'manual',
            ]);
        }

        $paidType = LeaveType::where('company_id', $company->id)->where('code', 'PHEP')->first();
        LeaveRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $paidType->id,
            'start_date' => '2026-05-14',
            'end_date' => '2026-05-14',
            'total_days' => 1,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $unpaidType = LeaveType::where('company_id', $company->id)->where('code', 'VIEC_RIENG')->first();
        LeaveRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $unpaidType->id,
            'start_date' => '2026-05-22',
            'end_date' => '2026-05-22',
            'total_days' => 1,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        return compact('company', 'employee');
    }

    public function test_summary_splits_paid_and_unpaid_leave_by_tv_ct(): void
    {
        $ctx = $this->seedEmployeeWithPhaseSplit();
        app(AttendanceSummaryService::class)->buildForPeriod($ctx['company']->id, '2026-05');

        $summary = \App\Models\AttendanceSummary::where('employee_id', $ctx['employee']->id)
            ->where('period', '2026-05')
            ->first();

        $this->assertNotNull($summary);
        $this->assertSame(1.0, (float) $summary->probation_paid_leave_days);
        $this->assertSame(0.0, (float) $summary->official_paid_leave_days);
        $this->assertSame(0.0, (float) $summary->probation_unpaid_leave_days);
        $this->assertSame(1.0, (float) $summary->official_unpaid_leave_days);
        $this->assertSame(1.0, (float) $summary->attendance_breakdown['leave_by_phase']['probation']['paid']);
        $this->assertSame(1.0, (float) $summary->attendance_breakdown['leave_by_phase']['official']['unpaid']);
    }

    public function test_payroll_uses_paid_leave_in_correct_phase_for_base_pay(): void
    {
        $ctx = $this->seedEmployeeWithPhaseSplit();
        app(AttendanceSummaryService::class)->buildForPeriod($ctx['company']->id, '2026-05');

        $summary = \App\Models\AttendanceSummary::where('employee_id', $ctx['employee']->id)
            ->where('period', '2026-05')
            ->first();
        $contract = EmploymentContract::where('employee_id', $ctx['employee']->id)->first();

        $result = app(PayrollEarningsService::class)->calculateGross(
            $ctx['employee'],
            $contract,
            $summary,
            '2026-05',
        );

        $this->assertTrue($result['breakdown']['has_phase_split']);
        $this->assertSame(1.0, $result['breakdown']['probation_paid_leave_days']);
        $this->assertSame(1.0, $result['breakdown']['official_unpaid_leave_days']);
        $this->assertGreaterThan(0, $result['breakdown']['probation_base_pay']);
        $this->assertGreaterThan(0, $result['breakdown']['official_base_pay']);

        $standard = (float) $summary->standard_work_days;
        $expectedProbationPay = round(17_000_000 / $standard * ((float) $summary->probation_work_days + 1), 0);
        $expectedOfficialPay = round(20_000_000 / $standard * (float) $summary->official_work_days, 0);
        $this->assertEquals($expectedProbationPay, $result['breakdown']['probation_base_pay']);
        $this->assertEquals($expectedOfficialPay, $result['breakdown']['official_base_pay']);
    }

    public function test_daily_timesheet_exposes_leave_split_totals(): void
    {
        $ctx = $this->seedEmployeeWithPhaseSplit();
        $timesheet = app(\App\Services\Attendance\AttendanceTimesheetService::class)
            ->dailyTimesheet($ctx['company']->id, '2026-05');
        $row = collect($timesheet['employees'])->firstWhere('employee_id', $ctx['employee']->id);

        $this->assertNotNull($row);
        $this->assertTrue($row['has_phase_split']);
        $this->assertSame(1.0, (float) $row['totals']['probation_paid_leave']);
        $this->assertSame(1.0, (float) $row['totals']['official_unpaid_leave']);
    }
}
