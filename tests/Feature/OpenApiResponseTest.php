<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OpenApiResponseTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        Role::firstOrCreate(['name' => 'admin']);

        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('Admin@123'),
        ]);

        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'admin-token'])->save();

        return $user;
    }

    public function test_company_response_contains_expected_fields()
    {
        $user = $this->createAdmin();
        $headers = ['Authorization' => 'Bearer ' . $user->api_token];

        $payload = [
            'name' => 'HRM Global',
            'code' => 'COMP-001',
            'tax_code' => 'HRM2026001',
            'address' => '123 Main Street, Hanoi',
            'phone' => '+84 24 1234 5678',
            'email' => 'contact@hrmglobal.local',
            'is_active' => true,
        ];

        $response = $this->withHeaders($headers)->postJson('/api/v1/companies', $payload);
        $response->assertCreated();
        $data = $response->json('data');

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayHasKey('created_at', $data);
    }

    public function test_employee_response_contains_expected_fields()
    {
        $user = $this->createAdmin();
        $headers = ['Authorization' => 'Bearer ' . $user->api_token];

        // create company/branch/department/position to satisfy FK
        $company = $this->withHeaders($headers)->postJson('/api/v1/companies', [
            'name' => 'HRM Global', 'code' => 'COMP-001', 'is_active' => true,
        ])->json('data');

        $branch = $this->withHeaders($headers)->postJson('/api/v1/branches', [
            'company_id' => $company['id'], 'name' => 'Hanoi', 'code' => 'BR-001',
        ])->json('data');

        $department = $this->withHeaders($headers)->postJson('/api/v1/departments', [
            'branch_id' => $branch['id'], 'name' => 'HR', 'code' => 'DEP-HR',
        ])->json('data');

        $position = $this->withHeaders($headers)->postJson('/api/v1/positions', [
            'department_id' => $department['id'], 'name' => 'HR Manager', 'code' => 'POS-HR',
        ])->json('data');

        $employeePayload = [
            'company_id' => $company['id'],
            'branch_id' => $branch['id'],
            'department_id' => $department['id'],
            'position_id' => $position['id'],
            'employee_code' => 'EMP-001',
            'first_name' => 'Nguyen',
            'last_name' => 'An',
            'full_name' => 'Nguyen An',
            'email' => 'nguyen.an@hrmglobal.local',
        ];

        $employeeResp = $this->withHeaders($headers)->postJson('/api/v1/employees', $employeePayload);
        $employeeResp->assertCreated();
        $emp = $employeeResp->json('data');

        $this->assertArrayHasKey('id', $emp);
        $this->assertArrayHasKey('employee_code', $emp);
        $this->assertArrayHasKey('first_name', $emp);
        $this->assertArrayHasKey('full_name', $emp);
    }
}
