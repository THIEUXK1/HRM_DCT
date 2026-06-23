<?php

namespace Tests\Feature;

use App\Models\AttendanceGeofenceZone;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceEmployeeDetailTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): array
    {
        Role::firstOrCreate(['name' => 'admin']);
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
            'employee_code' => 'NV001',
            'first_name' => 'Test',
            'last_name' => 'User',
            'full_name' => 'Test User',
            'email' => 'nv001@test.local',
            'hire_date' => '2026-05-01',
            'is_active' => true,
        ]);

        $zone = AttendanceGeofenceZone::create([
            'company_id' => $company->id,
            'code' => 'VP1',
            'name' => 'Văn phòng HN',
            'zone_type' => 'office',
            'latitude' => 21.0285,
            'longitude' => 105.8542,
            'radius_meters' => 200,
            'is_active' => true,
            'address_note' => 'Tầng 5, tòa A',
        ]);

        AttendanceLog::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-05-20',
            'check_in_at' => '2026-05-20 08:05:00',
            'check_out_at' => '2026-05-20 17:10:00',
            'source' => 'mobile',
            'location_status' => 'valid',
            'check_in_latitude' => 21.0285,
            'check_in_longitude' => 105.8542,
            'check_in_zone_id' => $zone->id,
            'check_out_zone_id' => $zone->id,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('Admin@123'),
            'default_company_id' => $company->id,
        ]);
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'tok-'.uniqid()])->save();

        return [$user, $company, $employee, $zone];
    }

    public function test_employee_detail_returns_daily_punch_and_location(): void
    {
        [$user, $company, $employee, $zone] = $this->actingAdmin();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->getJson('/api/v1/attendance-reports/employee-detail?'.http_build_query([
            'period' => '2026-05',
            'employee_id' => $employee->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.employee.employee_code', 'NV001');
        $response->assertJsonStructure([
            'data' => [
                'daily_rows',
                'employee' => ['full_name', 'department'],
                'totals',
            ],
        ]);

        $day = collect($response->json('data.daily_rows'))->firstWhere('date', '2026-05-20');
        $this->assertNotNull($day);
        $this->assertEquals('08:05', $day['check_in_at']);
        $this->assertEquals('17:10', $day['check_out_at']);
        $this->assertStringContainsString('Văn phòng HN', $day['check_in_location']['label']);
        $this->assertEquals('App di động', $day['source_label']);
    }

    public function test_export_employee_detail_returns_xlsx(): void
    {
        [$user, $company, $employee] = $this->actingAdmin();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->get('/api/v1/attendance-reports/export-employee-detail?'.http_build_query([
            'period' => '2026-05',
            'employee_id' => $employee->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
