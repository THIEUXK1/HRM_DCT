<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\AttendanceSummary;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\Tenant;
use App\Models\WorkShift;
use App\Services\Attendance\AttendanceSummaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private function seedCompanyWithEmployee(): array
    {
        $tenant = Tenant::create(['code' => 'T1', 'name' => 'T1']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'BP', 'name' => 'BestPacific']);

        WorkShift::create([
            'company_id' => $company->id,
            'code' => 'CA1',
            'name' => 'Ca 1',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_minutes' => 60,
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'V260865',
            'first_name' => 'Minh',
            'last_name' => 'Chang',
            'full_name' => 'Chang A Minh',
            'email' => 'minh@test.local',
            'hire_date' => '2026-04-17',
            'is_active' => true,
        ]);

        foreach (config('hr_vn.leave_types', []) as $def) {
            LeaveType::create(array_merge($def, ['company_id' => $company->id]));
        }

        return [$company, $employee];
    }

    public function test_build_summary_stores_ot_grid_and_leave_breakdown(): void
    {
        [$company, $employee] = $this->seedCompanyWithEmployee();
        $period = '2026-05';
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();

        AttendanceLog::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-05-20',
            'check_in_at' => '2026-05-20 08:00:00',
            'check_out_at' => '2026-05-20 17:00:00',
            'source' => 'manual',
        ]);

        OvertimeRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-05-20',
            'hours' => 32,
            'night_hours' => 4,
            'ot_type' => 'weekday',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        OvertimeRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-05-24',
            'hours' => 24,
            'night_hours' => 0,
            'ot_type' => 'weekend',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        OvertimeRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-05-25',
            'hours' => 24,
            'night_hours' => 0,
            'ot_type' => 'holiday',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $personalLeave = LeaveType::where('company_id', $company->id)->where('code', 'VIEC_RIENG')->first();
        LeaveRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $personalLeave->id,
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-13',
            'total_days' => 2,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        app(AttendanceSummaryService::class)->buildForPeriod($company->id, $period);

        $summary = AttendanceSummary::where('employee_id', $employee->id)->where('period', $period)->first();
        $this->assertNotNull($summary);
        $this->assertIsArray($summary->attendance_breakdown);

        $ot = $summary->attendance_breakdown['ot'];
        $this->assertEquals(28.0, $ot['day_weekday']);
        $this->assertEquals(4.0, $ot['night_weekday']);
        $this->assertEquals(24.0, $ot['day_weekend']);
        $this->assertEquals(24.0, $ot['day_holiday']);

        $leave = $summary->attendance_breakdown['leave_by_type'];
        $this->assertGreaterThanOrEqual(1.0, $leave['personal']);

        $work = $summary->attendance_breakdown['work'];
        $this->assertEquals(0.0, $work['days_not_joined']);
        $this->assertArrayHasKey('employment_status', $summary->attendance_breakdown['meta']);
    }
}
