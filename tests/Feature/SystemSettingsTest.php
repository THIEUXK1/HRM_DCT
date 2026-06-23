<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyHoliday;
use App\Models\CompanySetting;
use App\Models\JobLevel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(Tenant $tenant = null): User
    {
        Role::firstOrCreate(['name' => 'admin']);

        $user = User::factory()->create([
            'email' => 'admin_' . uniqid() . '@example.com',
            'password' => Hash::make('Admin@123'),
            'tenant_id' => $tenant ? $tenant->id : null,
        ]);

        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'admin-token-' . uniqid()])->save();

        return $user;
    }

    public function test_admin_can_perform_work_shift_crud(): void
    {
        $user = $this->createAdminUser();
        $headers = ['Authorization' => 'Bearer ' . $user->api_token];

        // 1. Create a Company to associate with
        $company = Company::create(['name' => 'Test Company', 'code' => 'TCOMP']);
        
        // Mock active company context
        \App\Support\CompanyContext::set($company->id);

        // 2. Test POST /api/v1/work-shifts
        $createData = [
            'code' => 'SHIFT_A',
            'name' => 'Ca sáng hành chính',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_minutes' => 60,
            'is_active' => true,
        ];

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/work-shifts', $createData);
        
        $response->assertCreated();
        $response->assertJsonPath('data.code', 'SHIFT_A');
        
        $shiftId = $response->json('data.id');

        // 3. Test GET /api/v1/work-shifts
        $indexResponse = $this->withHeaders($headers)->getJson('/api/v1/work-shifts');
        $indexResponse->assertOk();
        $indexResponse->assertJsonCount(1, 'data');

        // 4. Test PUT /api/v1/work-shifts/{id}
        $updateData = [
            'code' => 'SHIFT_A_UPDATED',
            'name' => 'Ca hành chính mới',
            'start_time' => '08:30',
            'end_time' => '17:30',
            'break_minutes' => 30,
            'is_active' => false,
        ];

        $updateResponse = $this->withHeaders($headers)
            ->putJson("/api/v1/work-shifts/{$shiftId}", $updateData);
        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('data.code', 'SHIFT_A_UPDATED');

        // 5. Test DELETE /api/v1/work-shifts/{id}
        $deleteResponse = $this->withHeaders($headers)->deleteJson("/api/v1/work-shifts/{$shiftId}");
        $deleteResponse->assertNoContent();
    }

    public function test_admin_can_get_and_update_company_settings(): void
    {
        $user = $this->createAdminUser();
        $headers = ['Authorization' => 'Bearer ' . $user->api_token];

        $company = Company::create(['name' => 'Test Company', 'code' => 'TCOMP']);
        \App\Support\CompanyContext::set($company->id);

        // 1. Get initial settings (seeded defaults from company creation or migration)
        $response = $this->withHeaders($headers)->getJson('/api/v1/company-settings');
        $response->assertOk();

        // 2. Post bulk updates
        $updateData = [
            'settings' => [
                'insurance_rate_employer' => '22.0',
                'annual_leave_standard' => '14',
                'standard_working_days' => '24',
            ]
        ];

        $updateResponse = $this->withHeaders($headers)
            ->postJson('/api/v1/company-settings', $updateData);
        
        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('data.insurance_rate_employer', '22.0');
        $updateResponse->assertJsonPath('data.annual_leave_standard', '14');
        $updateResponse->assertJsonPath('data.standard_working_days', '24');
    }

    public function test_admin_can_perform_job_level_crud(): void
    {
        $user = $this->createAdminUser();
        $headers = ['Authorization' => 'Bearer ' . $user->api_token];

        $company = Company::create(['name' => 'Test Company', 'code' => 'TCOMP']);
        \App\Support\CompanyContext::set($company->id);

        // 1. Test POST /api/v1/job-levels
        $createData = [
            'code' => 'LV_CEO',
            'name' => 'Giám đốc Điều hành',
            'rank' => 10,
            'basic_salary_range_min' => 100000000,
            'basic_salary_range_max' => 200000000,
            'is_active' => true,
        ];

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/job-levels', $createData);
        
        $response->assertCreated();
        $levelId = $response->json('data.id');

        // 2. Test GET /api/v1/job-levels
        $indexResponse = $this->withHeaders($headers)->getJson('/api/v1/job-levels');
        $indexResponse->assertOk();
        $levels = $indexResponse->json('data.levels') ?? $indexResponse->json('data');
        $this->assertCount(1, is_array($levels) ? $levels : []);

        // 3. Test DELETE /api/v1/job-levels/{id}
        $this->withHeaders($headers)->deleteJson("/api/v1/job-levels/{$levelId}")->assertNoContent();
    }

    public function test_admin_can_perform_company_holiday_crud(): void
    {
        $user = $this->createAdminUser();
        $headers = ['Authorization' => 'Bearer ' . $user->api_token];

        $company = Company::create(['name' => 'Test Company', 'code' => 'TCOMP']);
        \App\Support\CompanyContext::set($company->id);

        // 1. Test POST /api/v1/company-holidays
        $createData = [
            'name' => 'Ngày lễ Công ty Đặc biệt',
            'holiday_date' => '2026-12-25',
            'is_paid' => true,
        ];

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/company-holidays', $createData);
        
        $response->assertCreated();
        $holidayId = $response->json('data.holiday.id');

        // 2. Test GET /api/v1/company-holidays
        $indexResponse = $this->withHeaders($headers)->getJson('/api/v1/company-holidays');
        $indexResponse->assertOk();
        // Newly created company starts with 0 seeded values. 0 + 1 = 1 holiday.
        $indexResponse->assertJsonCount(1, 'data');

        // 3. Test DELETE /api/v1/company-holidays/{id}
        $this->withHeaders($headers)->deleteJson("/api/v1/company-holidays/{$holidayId}")->assertNoContent();
    }
}
