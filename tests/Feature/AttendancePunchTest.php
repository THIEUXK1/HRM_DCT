<?php

namespace Tests\Feature;

use App\Models\AttendanceGeofenceZone;
use App\Models\AttendanceDevice;
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

class AttendancePunchTest extends TestCase
{
    use RefreshDatabase;

    private function employeeUser(): array
    {
        Role::firstOrCreate(['name' => 'employee']);
        Permission::findOrCreate('attendance.punch_gps', 'web');
        Permission::findOrCreate('attendance.punch_qr', 'web');

        $tenant = Tenant::create(['code' => 'T1', 'name' => 'T1']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C1', 'name' => 'C1']);

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV-001',
            'first_name' => 'An',
            'last_name' => 'Nguyen',
            'full_name' => 'An Nguyen',
            'email' => 'an@test.local',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('Admin@123'),
            'employee_id' => $employee->id,
            'default_company_id' => $company->id,
        ]);
        $user->assignRole('employee');
        $user->givePermissionTo(['attendance.punch_gps', 'attendance.punch_qr']);
        $user->forceFill(['api_token' => 'emp-tok-'.uniqid()])->save();

        AttendanceGeofenceZone::create([
            'company_id' => $company->id,
            'code' => 'NM-MAIN',
            'name' => 'Nhà máy',
            'zone_type' => 'factory',
            'latitude' => 10.776889,
            'longitude' => 106.700806,
            'radius_meters' => 350,
            'allowed_sources' => ['mobile', 'device'],
            'is_active' => true,
        ]);

        CompanySetting::create(['company_id' => $company->id, 'key' => 'attendance_mobile_punch_enabled', 'value' => '1']);
        CompanySetting::create(['company_id' => $company->id, 'key' => 'attendance_geofence_strict', 'value' => '1']);

        return [$user, $company, $employee];
    }

    public function test_mobile_punch_inside_geofence(): void
    {
        [$user, $company] = $this->employeeUser();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/self-service/attendance/punch', [
            'punch_type' => 'in',
            'latitude' => 10.776900,
            'longitude' => 106.700820,
            'accuracy_meters' => 20,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.punch.punch_type', 'in')
            ->assertJsonPath('data.punch.is_valid', true);
    }

    public function test_mobile_punch_outside_geofence_rejected_when_strict(): void
    {
        [$user, $company] = $this->employeeUser();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/self-service/attendance/punch', [
            'punch_type' => 'in',
            'latitude' => 21.028511,
            'longitude' => 105.804817,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Bạn đang ngoài phạm vi chi nhánh được phép chấm công.']);
    }

    public function test_mobile_punch_rejected_when_inside_other_branch_zone(): void
    {
        [$user, $company, $employee] = $this->employeeUser();

        $branchA = \App\Models\Branch::create([
            'company_id' => $company->id,
            'code' => 'CN-A',
            'name' => 'Chi nhánh A',
            'is_active' => true,
        ]);
        $branchB = \App\Models\Branch::create([
            'company_id' => $company->id,
            'code' => 'CN-B',
            'name' => 'Chi nhánh B',
            'is_active' => true,
        ]);

        $employee->update(['branch_id' => $branchA->id]);

        AttendanceGeofenceZone::where('company_id', $company->id)->delete();

        AttendanceGeofenceZone::create([
            'company_id' => $company->id,
            'branch_id' => $branchB->id,
            'code' => 'CN-B-MAIN',
            'name' => 'VP Chi nhánh B',
            'zone_type' => 'office',
            'latitude' => 10.776889,
            'longitude' => 106.700806,
            'radius_meters' => 350,
            'allowed_sources' => ['mobile'],
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/self-service/attendance/punch', [
            'punch_type' => 'in',
            'latitude' => 10.776900,
            'longitude' => 106.700820,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Vị trí thuộc chi nhánh khác. Bạn chỉ được chấm công tại chi nhánh được phân công.']);
    }

    public function test_mobile_punch_allowed_in_own_branch_zone(): void
    {
        [$user, $company, $employee] = $this->employeeUser();

        $branch = \App\Models\Branch::create([
            'company_id' => $company->id,
            'code' => 'CN-A',
            'name' => 'Chi nhánh A',
            'is_active' => true,
        ]);
        $employee->update(['branch_id' => $branch->id]);

        AttendanceGeofenceZone::where('company_id', $company->id)->delete();

        AttendanceGeofenceZone::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'code' => 'CN-A-MAIN',
            'name' => 'VP Chi nhánh A',
            'zone_type' => 'office',
            'latitude' => 10.776889,
            'longitude' => 106.700806,
            'radius_meters' => 50,
            'allowed_sources' => ['mobile'],
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/self-service/attendance/punch', [
            'punch_type' => 'in',
            'latitude' => 10.776900,
            'longitude' => 106.700820,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.punch.is_valid', true);
    }

    public function test_device_punch_with_token(): void
    {
        [$user, $company, $employee] = $this->employeeUser();

        $device = AttendanceDevice::create([
            'company_id' => $company->id,
            'name' => 'Máy cổng A',
            'code' => 'DEV-A',
            'device_type' => 'terminal',
            'is_active' => true,
        ]);

        $issued = AttendanceDevice::issueApiToken($device);

        $response = $this->withHeaders([
            'X-Device-Token' => $issued['token'],
        ])->postJson('/api/v1/attendance/device-punch', [
            'employee_code' => $employee->employee_code,
            'punch_type' => 'in',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.punch.source', 'device');
    }

    public function test_qr_gate_punch_without_gps(): void
    {
        [$user, $company] = $this->employeeUser();

        $zone = AttendanceGeofenceZone::where('company_id', $company->id)->first();
        $issued = $zone->issueGateToken();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/self-service/attendance/punch', [
            'punch_type' => 'in',
            'zone_code' => $zone->code,
            'gate_token' => $issued['gate_token'],
            'source' => 'qr',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.punch.source', 'qr')
            ->assertJsonPath('data.punch.is_valid', true);
    }

    public function test_qr_payload_parsing_on_punch(): void
    {
        [$user, $company] = $this->employeeUser();

        $zone = AttendanceGeofenceZone::where('company_id', $company->id)->first();
        $issued = $zone->issueGateToken();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/self-service/attendance/punch', [
            'punch_type' => 'in',
            'qr_payload' => $issued['qr_payload'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.log.location_status', 'qr_gate');
    }
}
