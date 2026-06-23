<?php



namespace Tests\Feature;



use App\Models\AttendanceGeofenceZone;

use App\Models\Company;

use App\Models\CompanySetting;

use App\Models\Employee;

use App\Models\Tenant;

use App\Models\User;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Illuminate\Support\Facades\Hash;

use Spatie\Permission\Models\Permission;

use Spatie\Permission\Models\Role;

use Tests\TestCase;



class AttendancePunchAuthTest extends TestCase

{

    use RefreshDatabase;



    private function punchEmployee(bool $gps = true, bool $qr = false, bool $mustChangePassword = true): array

    {

        Permission::findOrCreate('attendance.punch_gps', 'web');

        Permission::findOrCreate('attendance.punch_qr', 'web');

        Role::firstOrCreate(['name' => 'employee']);



        $tenant = Tenant::create(['code' => 'T1', 'name' => 'T1']);

        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C1', 'name' => 'C1']);



        $employee = Employee::create([

            'company_id' => $company->id,

            'employee_code' => 'NV-PUNCH',

            'first_name' => 'Punch',

            'last_name' => 'Test',

            'full_name' => 'Punch Test',

            'email' => 'punch@test.local',

            'is_active' => true,

        ]);



        $user = User::factory()->create([

            'tenant_id' => $tenant->id,

            'password' => Hash::make('abc@123'),

            'employee_id' => $employee->id,

            'default_company_id' => $company->id,

            'must_change_password' => $mustChangePassword,

        ]);

        $user->assignRole('employee');

        if ($gps) {

            $user->givePermissionTo('attendance.punch_gps');

        }

        if ($qr) {

            $user->givePermissionTo('attendance.punch_qr');

        }

        $user->forceFill(['api_token' => 'punch-auth-'.uniqid()])->save();



        AttendanceGeofenceZone::create([

            'company_id' => $company->id,

            'code' => 'NM-MAIN',

            'name' => 'Nhà máy',

            'zone_type' => 'factory',

            'latitude' => 10.776889,

            'longitude' => 106.700806,

            'radius_meters' => 350,

            'allowed_sources' => ['mobile', 'qr'],

            'is_active' => true,

        ]);



        CompanySetting::create(['company_id' => $company->id, 'key' => 'attendance_mobile_punch_enabled', 'value' => '1']);

        CompanySetting::create(['company_id' => $company->id, 'key' => 'attendance_geofence_strict', 'value' => '1']);



        return [$user, $company, $employee];

    }



    public function test_login_by_employee_code(): void

    {

        $this->punchEmployee();



        $response = $this->postJson('/api/v1/auth/login', [

            'login' => 'NV-PUNCH',

            'password' => 'abc@123',

        ]);



        $response->assertOk()

            ->assertJsonPath('data.must_change_password', true)

            ->assertJsonPath('data.user.employee_code', 'NV-PUNCH');

    }



    public function test_login_by_employee_code_is_case_insensitive(): void
    {
        $this->punchEmployee();

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'nv-punch',
            'password' => 'abc@123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.login', 'NV-PUNCH')
            ->assertJsonPath('data.user.employee_code', 'NV-PUNCH');
    }

    public function test_punch_denied_without_gps_permission(): void

    {

        [$user, $company] = $this->punchEmployee(gps: false, qr: false, mustChangePassword: false);



        $response = $this->withHeaders([

            'Authorization' => 'Bearer '.$user->api_token,

            'X-Company-Id' => $company->id,

        ])->postJson('/api/v1/self-service/attendance/punch', [

            'punch_type' => 'in',

            'latitude' => 10.776900,

            'longitude' => 106.700820,

            'accuracy_meters' => 20,

        ]);



        $response->assertStatus(422)

            ->assertJsonFragment(['message' => 'Tài khoản chưa được cấp quyền chấm công GPS. Liên hệ HR.']);

    }



    public function test_provision_punch_account(): void

    {

        Permission::findOrCreate('attendance.punch_accounts.manage', 'web');
        Permission::findOrCreate('employees.view', 'web');
        Role::firstOrCreate(['name' => 'hr_manager']);



        $tenant = Tenant::create(['code' => 'T1', 'name' => 'T1']);

        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C1', 'name' => 'C1']);

        $employee = Employee::create([

            'company_id' => $company->id,

            'employee_code' => 'NV-NEW',

            'first_name' => 'New',

            'last_name' => 'Emp',

            'full_name' => 'New Emp',

            'email' => 'new@test.local',

            'is_active' => true,

        ]);



        $hr = User::factory()->create([

            'tenant_id' => $tenant->id,

            'default_company_id' => $company->id,

        ]);

        $hr->assignRole('hr_manager');
        $hr->givePermissionTo(['attendance.punch_accounts.manage', 'employees.view']);
        $hr->forceFill(['api_token' => 'hr-'.uniqid()])->save();



        $response = $this->withHeaders([

            'Authorization' => 'Bearer '.$hr->api_token,

            'X-Company-Id' => $company->id,

        ])->postJson("/api/v1/employees/{$employee->id}/punch-account", [

            'punch_gps' => true,

            'punch_qr' => true,

        ]);



        $response->assertCreated()

            ->assertJsonPath('data.login', 'NV-NEW')

            ->assertJsonPath('data.default_password', 'abc@123');



        $this->assertDatabaseHas('users', [

            'employee_id' => $employee->id,

            'must_change_password' => true,

        ]);

    }



    public function test_change_password_clears_must_change_flag(): void

    {

        [$user] = $this->punchEmployee();



        $response = $this->withHeaders([

            'Authorization' => 'Bearer '.$user->api_token,

        ])->postJson('/api/v1/auth/change-password', [

            'current_password' => 'abc@123',

            'password' => 'NewPass1',

            'password_confirmation' => 'NewPass1',

        ]);



        $response->assertOk();

        $user->refresh();

        $this->assertFalse($user->must_change_password);

    }



    public function test_punch_blocked_until_password_changed(): void
    {
        [$user, $company] = $this->punchEmployee();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/self-service/attendance/punch', [
            'punch_type' => 'in',
            'latitude' => 10.776900,
            'longitude' => 106.700820,
            'accuracy_meters' => 20,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Bạn phải đổi mật khẩu trước khi chấm công.']);
    }

    public function test_duplicate_check_in_rejected(): void

    {

        [$user, $company] = $this->punchEmployee(mustChangePassword: false);



        $headers = [

            'Authorization' => 'Bearer '.$user->api_token,

            'X-Company-Id' => $company->id,

        ];

        $payload = [

            'punch_type' => 'in',

            'latitude' => 10.776900,

            'longitude' => 106.700820,

            'accuracy_meters' => 20,

        ];



        $this->withHeaders($headers)->postJson('/api/v1/self-service/attendance/punch', $payload)->assertCreated();



        $this->withHeaders($headers)->postJson('/api/v1/self-service/attendance/punch', $payload)

            ->assertStatus(422)

            ->assertJsonFragment(['message' => 'Bạn đã chấm vào hôm nay. Nếu nhầm, liên hệ HR điều chỉnh bảng công.']);

    }

}


