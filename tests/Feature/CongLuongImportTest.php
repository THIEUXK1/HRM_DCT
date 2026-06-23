<?php

namespace Tests\Feature;

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

class CongLuongImportTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithEmployees(): array
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
            'is_active' => true,
        ]);

        Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'V260865',
            'first_name' => 'Minh',
            'last_name' => 'Chang A',
            'full_name' => 'Chang A Minh',
            'email' => 'v260865@test.local',
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

    public function test_imports_cong_and_luong_from_sample_xlsx(): void
    {
        [$user, $company] = $this->adminWithEmployees();

        $sample = storage_path('app/templates/cong-va-luong-mau.xlsx');
        if (! is_readable($sample)) {
            $this->markTestSkipped('File mẫu cong-va-luong-mau.xlsx chưa có trong storage/app/templates.');
        }

        $file = new UploadedFile($sample, 'cong-luong.xlsx', null, null, true);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->post('/api/v1/payroll-import/cong-luong', [
            'period' => '2026-04',
            'file' => $file,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.cong.imported', 2);
        $response->assertJsonPath('data.luong.imported', 2);

        $this->assertDatabaseHas('attendance_summaries', [
            'company_id' => $company->id,
            'period' => '2026-04',
        ]);

        $this->assertDatabaseHas('employee_payroll_allowances', [
            'company_id' => $company->id,
            'period' => '2026-04',
        ]);

        $sheet = EmployeePayrollAllowance::where('company_id', $company->id)
            ->where('period', '2026-04')
            ->whereHas('employee', fn ($q) => $q->where('employee_code', 'V260864'))
            ->first();

        $this->assertNotNull($sheet);
        $this->assertSame(1000000.0, (float) ($sheet->allowances['allowance_position'] ?? 0));
    }
}
