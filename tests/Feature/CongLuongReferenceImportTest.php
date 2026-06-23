<?php

namespace Tests\Feature;

use App\Models\AttendanceSummary;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeePayrollAllowance;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CongLuongReferenceImportTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithReferenceEmployees(): array
    {
        Role::firstOrCreate(['name' => 'admin']);

        $tenant = Tenant::create(['code' => 'T1', 'name' => 'Tenant 1']);
        $company = Company::create([
            'tenant_id' => $tenant->id,
            'code' => 'BPVN',
            'name' => 'Công ty test',
        ]);

        Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'V260864',
            'first_name' => 'Hà',
            'last_name' => 'Sầm Văn',
            'full_name' => 'Sầm Văn Hà',
            'email' => 'v260864@test.local',
            'hire_date' => '2026-04-16',
            'is_active' => true,
        ]);

        Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'V260865',
            'first_name' => 'Minh',
            'last_name' => 'Chang A',
            'full_name' => 'Chang A Minh',
            'email' => 'v260865@test.local',
            'hire_date' => '2026-04-17',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('Admin@123'),
            'default_company_id' => $company->id,
        ]);
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'admin-tok-'.uniqid()])->save();

        return [$user, $company];
    }

    public function test_imports_reference_cong_luong_template_for_mid_month_join_cases(): void
    {
        [$user, $company] = $this->adminWithReferenceEmployees();

        $sample = storage_path('app/templates/cong-va-luong-mau.xlsx');
        if (! is_readable($sample)) {
            $this->markTestSkipped('File mẫu cong-va-luong-mau.xlsx chưa có.');
        }

        $file = new UploadedFile($sample, 'cong-luong.xlsx', null, null, true);
        $period = '2026-04';

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->post('/api/v1/payroll-import/cong-luong', [
            'period' => $period,
            'file' => $file,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.cong.imported', 2);
        $response->assertJsonPath('data.luong.imported', 2);

        $official = AttendanceSummary::whereHas('employee', fn ($q) => $q->where('employee_code', 'V260864'))
            ->where('period', $period)
            ->first();

        $this->assertNotNull($official);
        $this->assertSame(9.5, (float) $official->work_days);
        $this->assertSame(13.0, (float) ($official->attendance_breakdown['work']['days_not_joined'] ?? 0));
        $this->assertSame('正式', $official->attendance_breakdown['meta']['employment_status_raw'] ?? '');
        $this->assertSame(0.0, (float) $official->probation_work_days);
        $this->assertSame(9.5, (float) $official->official_work_days);
        $this->assertSame(59.0, (float) ($official->attendance_breakdown['ot']['day_weekday'] ?? 0));

        $probation = AttendanceSummary::whereHas('employee', fn ($q) => $q->where('employee_code', 'V260865'))
            ->where('period', $period)
            ->first();

        $this->assertNotNull($probation);
        $this->assertSame(8.5, (float) $probation->work_days);
        $this->assertSame(14.0, (float) ($probation->attendance_breakdown['work']['days_not_joined'] ?? 0));
        $this->assertSame('试用', $probation->attendance_breakdown['meta']['employment_status_raw'] ?? '');
        $this->assertSame(8.5, (float) $probation->probation_work_days);
        $this->assertSame(0.0, (float) $probation->official_work_days);
        $this->assertSame(24.0, (float) ($probation->attendance_breakdown['ot']['day_holiday'] ?? 0));

        $sheetOfficial = EmployeePayrollAllowance::whereHas('employee', fn ($q) => $q->where('employee_code', 'V260864'))
            ->where('period', $period)
            ->first();
        $this->assertSame(1_000_000.0, (float) ($sheetOfficial->allowances['allowance_position'] ?? 0));

        $sheetProbation = EmployeePayrollAllowance::whereHas('employee', fn ($q) => $q->where('employee_code', 'V260865'))
            ->where('period', $period)
            ->first();
        $this->assertSame(275_000.0, (float) ($sheetProbation->allowances['allowance_position'] ?? 0));
        $this->assertSame(1_241_625.0, (float) ($sheetProbation->allowances['allowance_probation_insurance'] ?? 0));
    }
}
