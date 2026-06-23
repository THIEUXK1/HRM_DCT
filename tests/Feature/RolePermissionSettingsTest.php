<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RolePermissionSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
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

    public function test_admin_can_perform_role_crud_and_sync_permissions(): void
    {
        $user = $this->createAdminUser();
        $headers = ['Authorization' => 'Bearer ' . $user->api_token];

        // 1. Tạo mới quyền giả lập để test gán quyền
        $permission1 = Permission::firstOrCreate(['name' => 'employees.view', 'guard_name' => 'web']);
        $permission2 = Permission::firstOrCreate(['name' => 'employees.create', 'guard_name' => 'web']);

        // 2. Test POST /api/v1/roles (tạo mới vai trò)
        $createData = [
            'name' => 'hr_assistant',
            'guard_name' => 'web',
        ];

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/roles', $createData);
        
        $response->assertCreated();
        $response->assertJsonPath('data.name', 'hr_assistant');
        $roleId = $response->json('data.id');

        // 3. Test GET /api/v1/roles (lấy danh sách vai trò)
        $indexResponse = $this->withHeaders($headers)->getJson('/api/v1/roles');
        $indexResponse->assertOk();
        // Spatie seeder hoặc mặc định tạo 'admin' + 'hr_assistant'
        $this->assertDatabaseHas('roles', ['name' => 'hr_assistant']);

        // 4. Test PUT /api/v1/roles/{id} (cập nhật tên và đồng bộ quyền hạn)
        $updateData = [
            'name' => 'hr_assistant_updated',
            'permissions' => ['employees.view', 'employees.create']
        ];

        $updateResponse = $this->withHeaders($headers)
            ->putJson("/api/v1/roles/{$roleId}", $updateData);
        
        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('data.name', 'hr_assistant_updated');
        
        // Xác minh vai trò mới đã được đồng bộ 2 quyền hạn
        $role = Role::findById($roleId);
        $this->assertTrue($role->hasPermissionTo('employees.view'));
        $this->assertTrue($role->hasPermissionTo('employees.create'));

        // 5. Test DELETE /api/v1/roles/{id} (xóa vai trò tùy chỉnh)
        $deleteResponse = $this->withHeaders($headers)->deleteJson("/api/v1/roles/{$roleId}");
        $deleteResponse->assertNoContent();
        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }

    public function test_deleting_core_roles_is_rejected(): void
    {
        $user = $this->createAdminUser();
        $headers = ['Authorization' => 'Bearer ' . $user->api_token];

        // Vai trò admin là vai trò cốt lõi
        $adminRole = Role::findByName('admin');

        $response = $this->withHeaders($headers)->deleteJson("/api/v1/roles/{$adminRole->id}");
        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Không thể xóa vai trò mặc định của hệ thống.');
    }

    public function test_admin_can_assign_roles_to_user(): void
    {
        $admin = $this->createAdminUser();
        $headers = ['Authorization' => 'Bearer ' . $admin->api_token];

        // Tạo một vai trò phụ
        $managerRole = Role::firstOrCreate(['name' => 'department_manager', 'guard_name' => 'web']);

        // Tạo một người dùng thường
        $targetUser = User::factory()->create([
            'email' => 'staff@example.com',
            'name' => 'Staff Member'
        ]);

        // Gán vai trò cho người dùng
        $syncData = [
            'roles' => ['department_manager']
        ];

        $response = $this->withHeaders($headers)
            ->putJson("/api/v1/users/{$targetUser->id}/roles", $syncData);
        
        $response->assertOk();
        
        // Xác minh targetUser đã có vai trò mới
        $targetUser->refresh();
        $this->assertTrue($targetUser->hasRole('department_manager'));
    }

    public function test_get_permissions_returns_list(): void
    {
        $user = $this->createAdminUser();
        $headers = ['Authorization' => 'Bearer ' . $user->api_token];

        Permission::firstOrCreate(['name' => 'employees.view', 'guard_name' => 'web']);

        $response = $this->withHeaders($headers)->getJson('/api/v1/permissions');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_department_secretary_can_only_see_employees_within_their_department(): void
    {
        // 1. Tạo 2 phòng ban
        $company = \App\Models\Company::create(['name' => 'Test Company', 'code' => 'TCOMP']);
        $branch = \App\Models\Branch::create(['company_id' => $company->id, 'name' => 'Chi nhánh HN', 'code' => 'CNHN']);
        $dept1 = \App\Models\Department::create(['branch_id' => $branch->id, 'name' => 'Phòng Nhân sự', 'code' => 'HR']);
        $dept2 = \App\Models\Department::create(['branch_id' => $branch->id, 'name' => 'Phòng Kinh doanh', 'code' => 'SALES']);

        // 2. Tạo nhân viên Thư ký bộ phận trong phòng HR
        $empSecretary = \App\Models\Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $dept1->id,
            'first_name' => 'Hoa',
            'last_name' => 'Nguyen',
            'full_name' => 'Nguyen Thi Hoa',
            'employee_code' => 'NV-HR-01',
            'email' => 'secretary@example.com'
        ]);

        // Tạo 1 nhân viên khác thuộc phòng HR
        $empHR = \App\Models\Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $dept1->id,
            'first_name' => 'An',
            'last_name' => 'Tran',
            'full_name' => 'Tran Van An',
            'employee_code' => 'NV-HR-02',
            'email' => 'hr_staff@example.com'
        ]);

        // Tạo 1 nhân viên thuộc phòng SALES (phòng khác)
        $empSales = \App\Models\Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $dept2->id,
            'first_name' => 'Cuong',
            'last_name' => 'Le',
            'full_name' => 'Le Van Cuong',
            'employee_code' => 'NV-SALES-01',
            'email' => 'sales_staff@example.com'
        ]);

        // 3. Tạo tài khoản User gán vai trò Thư ký bộ phận (department_secretary)
        Role::firstOrCreate(['name' => 'department_secretary']);
        $user = User::factory()->create([
            'email' => 'secretary@example.com',
            'employee_id' => $empSecretary->id,
        ]);
        $user->assignRole('department_secretary');
        $user->forceFill(['api_token' => 'secretary-token'])->save();

        // Cấp quyền employees.view để Thư ký bộ phận có thể xem nhân viên
        $permEmployeesView = Permission::firstOrCreate(['name' => 'employees.view', 'guard_name' => 'web']);
        Role::findByName('department_secretary')->givePermissionTo($permEmployeesView);

        // 4. Test GET /api/v1/employees bằng tài khoản thư ký bộ phận
        $response = $this->withHeaders([
            'Authorization' => 'Bearer secretary-token',
            'X-Company-Id' => $company->id,
        ])->getJson('/api/v1/employees');
        
        $response->assertOk();
        // Nên chỉ thấy 2 nhân viên thuộc phòng HR (bản thân + nhân viên phòng HR khác), KHÔNG được thấy nhân viên phòng SALES
        $response->assertJsonCount(2, 'data.data');
        $employeeIds = collect($response->json('data.data'))->pluck('id')->toArray();
        
        $this->assertContains($empSecretary->id, $employeeIds);
        $this->assertContains($empHR->id, $employeeIds);
        $this->assertNotContains($empSales->id, $employeeIds);
    }

    public function test_department_secretary_can_only_see_attendance_summaries_within_their_department(): void
    {
        // 1. Tạo 2 phòng ban
        $company = \App\Models\Company::create(['name' => 'Test Company', 'code' => 'TCOMP']);
        $branch = \App\Models\Branch::create(['company_id' => $company->id, 'name' => 'Chi nhánh HN', 'code' => 'CNHN']);
        $dept1 = \App\Models\Department::create(['branch_id' => $branch->id, 'name' => 'Phòng Nhân sự', 'code' => 'HR']);
        $dept2 = \App\Models\Department::create(['branch_id' => $branch->id, 'name' => 'Phòng Kinh doanh', 'code' => 'SALES']);

        // 2. Tạo nhân viên Thư ký bộ phận trong phòng HR
        $empSecretary = \App\Models\Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $dept1->id,
            'first_name' => 'Hoa',
            'last_name' => 'Nguyen',
            'full_name' => 'Nguyen Thi Hoa',
            'employee_code' => 'NV-HR-01',
            'email' => 'secretary@example.com'
        ]);

        // Tạo 1 nhân viên khác thuộc phòng HR
        $empHR = \App\Models\Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $dept1->id,
            'first_name' => 'An',
            'last_name' => 'Tran',
            'full_name' => 'Tran Van An',
            'employee_code' => 'NV-HR-02',
            'email' => 'hr_staff@example.com'
        ]);

        // Tạo 1 nhân viên thuộc phòng SALES
        $empSales = \App\Models\Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $dept2->id,
            'first_name' => 'Cuong',
            'last_name' => 'Le',
            'full_name' => 'Le Van Cuong',
            'employee_code' => 'NV-SALES-01',
            'email' => 'sales_staff@example.com'
        ]);

        // 3. Tạo dữ liệu AttendanceSummary giả lập cho cả 3
        \App\Models\AttendanceSummary::create([
            'company_id' => $company->id,
            'employee_id' => $empSecretary->id,
            'period' => '2026-05',
            'work_days' => 20,
            'leave_days' => 2,
            'ot_hours' => 4,
            'late_minutes' => 15,
            'is_locked' => false,
        ]);

        \App\Models\AttendanceSummary::create([
            'company_id' => $company->id,
            'employee_id' => $empHR->id,
            'period' => '2026-05',
            'work_days' => 22,
            'leave_days' => 0,
            'ot_hours' => 8,
            'late_minutes' => 0,
            'is_locked' => false,
        ]);

        \App\Models\AttendanceSummary::create([
            'company_id' => $company->id,
            'employee_id' => $empSales->id,
            'period' => '2026-05',
            'work_days' => 21,
            'leave_days' => 1,
            'ot_hours' => 2,
            'late_minutes' => 30,
            'is_locked' => false,
        ]);

        // 4. Tạo tài khoản User gán vai trò Thư ký bộ phận
        Role::firstOrCreate(['name' => 'department_secretary']);
        $user = User::factory()->create([
            'email' => 'secretary@example.com',
            'employee_id' => $empSecretary->id,
        ]);
        $user->assignRole('department_secretary');
        $user->forceFill(['api_token' => 'secretary-token'])->save();

        // Thêm quyền attendance.view cho vai trò department_secretary để vượt qua middleware
        $permission = Permission::firstOrCreate(['name' => 'attendance.view']);
        Role::findByName('department_secretary')->givePermissionTo($permission);

        // 5. Test GET /api/v1/attendance-summaries
        $response = $this->withHeaders([
            'Authorization' => 'Bearer secretary-token',
            'X-Company-Id' => $company->id,
        ])->getJson('/api/v1/attendance-summaries?company_id=' . $company->id . '&period=2026-05');

        $response->assertOk();
        // Chỉ được thấy 2 bản ghi tổng hợp công thuộc phòng HR, KHÔNG thấy SALES
        $response->assertJsonCount(2, 'data.summaries');
        $employeeIds = collect($response->json('data.summaries'))->pluck('employee_id')->toArray();

        $this->assertContains($empSecretary->id, $employeeIds);
        $this->assertContains($empHR->id, $employeeIds);
        $this->assertNotContains($empSales->id, $employeeIds);
    }

    public function test_department_secretary_can_create_and_approve_overtime_requests_within_their_department(): void
    {
        // 1. Tạo phòng ban & Nhân viên
        $company = \App\Models\Company::create(['name' => 'Test Company', 'code' => 'TCOMP']);
        $branch = \App\Models\Branch::create(['company_id' => $company->id, 'name' => 'Chi nhánh HN', 'code' => 'CNHN']);
        $dept1 = \App\Models\Department::create(['branch_id' => $branch->id, 'name' => 'Phòng Nhân sự', 'code' => 'HR']);
        $dept2 = \App\Models\Department::create(['branch_id' => $branch->id, 'name' => 'Phòng Kinh doanh', 'code' => 'SALES']);

        $empSecretary = \App\Models\Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $dept1->id,
            'first_name' => 'Hoa',
            'last_name' => 'Nguyen',
            'full_name' => 'Nguyen Thi Hoa',
            'employee_code' => 'NV-HR-01',
            'email' => 'secretary@example.com'
        ]);

        $empHR = \App\Models\Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $dept1->id,
            'first_name' => 'An',
            'last_name' => 'Tran',
            'full_name' => 'Tran Van An',
            'employee_code' => 'NV-HR-02',
            'email' => 'hr_staff@example.com'
        ]);

        $empSales = \App\Models\Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $dept2->id,
            'first_name' => 'Cuong',
            'last_name' => 'Le',
            'full_name' => 'Le Van Cuong',
            'employee_code' => 'NV-SALES-01',
            'email' => 'sales_staff@example.com'
        ]);

        // 2. Tạo tài khoản User Thư ký bộ phận
        Role::firstOrCreate(['name' => 'department_secretary']);
        $user = User::factory()->create([
            'email' => 'secretary@example.com',
            'employee_id' => $empSecretary->id,
        ]);
        $user->assignRole('department_secretary');
        $user->forceFill(['api_token' => 'secretary-token'])->save();

        // Gán quyền leave.manage (được xem/tạo/duyệt OT/phép)
        $permLeaveView = Permission::firstOrCreate(['name' => 'leave.view']);
        $permLeaveManage = Permission::firstOrCreate(['name' => 'leave.manage']);
        Role::findByName('department_secretary')->givePermissionTo([$permLeaveView, $permLeaveManage]);

        // 3. Test POST /api/v1/overtime-requests (Tạo đơn OT cho nhân viên HR - thuộc phòng ban mình)
        $otData = [
            'company_id' => $company->id,
            'employee_id' => $empHR->id,
            'work_date' => '2026-05-20',
            'hours' => 3.5,
            'reason' => 'Làm báo cáo quyết toán',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer secretary-token',
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/overtime-requests', $otData);
        $response->assertCreated();
        $otId = $response->json('data.ot.id');

        // 4. Test Duyệt đơn OT
        $approveResponse = $this->withHeaders([
            'Authorization' => 'Bearer secretary-token',
            'X-Company-Id' => $company->id,
        ])->postJson("/api/v1/overtime-requests/{$otId}/approve");
        $approveResponse->assertOk();
        $this->assertEquals('approved', $approveResponse->json('data.status'));

        // 5. Kiểm tra lấy danh sách đơn làm thêm giờ (phải được scoped)
        // Tạo thêm 1 đơn OT của nhân viên SALES (phòng khác) để kiểm tra scoping
        \App\Models\OvertimeRequest::create([
            'company_id' => $company->id,
            'employee_id' => $empSales->id,
            'work_date' => '2026-05-20',
            'hours' => 2.0,
            'reason' => 'Tăng ca SALES',
            'status' => 'pending'
        ]);

        $listResponse = $this->withHeaders([
            'Authorization' => 'Bearer secretary-token',
            'X-Company-Id' => $company->id,
        ])->getJson('/api/v1/overtime-requests');
        $listResponse->assertOk();
        // Chỉ được xem đơn OT thuộc phòng HR (đơn của empHR vừa tạo), KHÔNG thấy đơn của empSales
        $listResponse->assertJsonCount(1, 'data.data');
        $this->assertEquals($empHR->id, $listResponse->json('data.data.0.employee_id'));
    }
}
