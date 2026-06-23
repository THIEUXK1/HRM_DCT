<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionReason;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Attendance\DiligenceBonusEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithCompany(): array
    {
        Role::firstOrCreate(['name' => 'admin']);
        $tenant = Tenant::create(['code' => 'T1', 'name' => 'T1']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C1', 'name' => 'C1']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('Admin@123'),
        ]);
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'tok-'.uniqid()])->save();

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'E-001',
            'first_name' => 'Test',
            'last_name' => 'User',
            'full_name' => 'Test User',
            'email' => uniqid().'@test.local',
            'is_active' => true,
        ]);

        \App\Support\CompanyContext::set($company->id);

        return [$user, $company, $employee];
    }

    public function test_forgot_punch_over_limit_disqualifies_bonus(): void
    {
        [$user, $company, $employee] = $this->adminWithCompany();

        CompanySetting::create(['company_id' => $company->id, 'key' => 'diligence_max_forgot_punch', 'value' => '2']);
        CompanySetting::create(['company_id' => $company->id, 'key' => 'diligence_bonus_amount', 'value' => '500000']);

        $reason = AttendanceCorrectionReason::create([
            'company_id' => $company->id,
            'code' => 'QUEN',
            'name' => 'Quên chấm công',
            'counts_as_forgot_punch' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        foreach (['2026-05-05', '2026-05-06', '2026-05-07'] as $date) {
            AttendanceCorrectionRequest::create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'correction_reason_id' => $reason->id,
                'work_date' => $date,
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        }

        $service = app(\App\Services\Attendance\AttendanceCorrectionService::class);
        $counts = $service->countsForEmployeePeriod(
            $employee->id,
            \Carbon\Carbon::parse('2026-05-01'),
            \Carbon\Carbon::parse('2026-05-31'),
        );

        $this->assertEquals(3, $counts['forgot_punch_count']);

        $summary = new \App\Models\AttendanceSummary([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period' => '2026-05',
            'standard_work_days' => 25,
            'work_days' => 25,
            'late_count' => 0,
            'absent_days' => 0,
            'forgot_punch_count' => 3,
        ]);

        $eval = app(DiligenceBonusEvaluator::class)->evaluate($summary, 100.0);
        $this->assertFalse($eval['eligible']);
        $this->assertStringContainsString('Quên chấm công', $eval['disqualify_reasons'][0]);
    }

    public function test_admin_can_manage_correction_reasons(): void
    {
        [$user, $company] = $this->adminWithCompany();
        $headers = ['Authorization' => 'Bearer '.$user->api_token, 'X-Company-Id' => $company->id];

        $response = $this->withHeaders($headers)->postJson('/api/v1/attendance-correction-reasons', [
            'code' => 'TAC_DUONG',
            'name' => 'Tắc đường',
            'counts_as_forgot_punch' => false,
            'sort_order' => 5,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('attendance_correction_reasons', [
            'company_id' => $company->id,
            'code' => 'TAC_DUONG',
        ]);
    }

    public function test_can_create_check_in_only_correction_request(): void
    {
        [$user, $company, $employee] = $this->adminWithCompany();
        $headers = ['Authorization' => 'Bearer '.$user->api_token, 'X-Company-Id' => $company->id];

        $reason = AttendanceCorrectionReason::create([
            'company_id' => $company->id,
            'code' => 'LOI_MAY',
            'name' => 'Lỗi máy',
            'counts_as_forgot_punch' => false,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $response = $this->withHeaders($headers)->postJson('/api/v1/attendance-correction-requests', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'correction_reason_id' => $reason->id,
            'correction_mode' => 'check_in',
            'work_date' => '2026-06-01',
            'requested_check_in_at' => '2026-06-01 08:15:00',
            'requested_check_out_at' => null,
            'note' => 'Quên chấm giờ vào',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.created_count', 1);

        $this->assertDatabaseHas('attendance_correction_requests', [
            'employee_id' => $employee->id,
            'requested_check_in_at' => '2026-06-01 08:15:00',
            'requested_check_out_at' => null,
        ]);
    }

    public function test_rejects_check_out_mode_when_check_out_time_missing(): void
    {
        [$user, $company, $employee] = $this->adminWithCompany();
        $headers = ['Authorization' => 'Bearer '.$user->api_token, 'X-Company-Id' => $company->id];

        $reason = AttendanceCorrectionReason::create([
            'company_id' => $company->id,
            'code' => 'KHAC',
            'name' => 'Khác',
            'counts_as_forgot_punch' => false,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $response = $this->withHeaders($headers)->postJson('/api/v1/attendance-correction-requests', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'correction_reason_id' => $reason->id,
            'correction_mode' => 'check_out',
            'work_date' => '2026-06-01',
            'requested_check_in_at' => null,
            'requested_check_out_at' => null,
        ]);

        $response->assertStatus(422);
    }
}
