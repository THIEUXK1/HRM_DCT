<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAwardDiscipline;
use App\Models\EmployeeTransfer;
use App\Models\EmployeeTermination;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeDecisionsTest extends TestCase
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

    private function setupEmployee(): array
    {
        $company = Company::create(['name' => 'Test Company', 'code' => 'TCOMP']);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Chi nhánh HN', 'code' => 'CNHN']);
        $dept = Department::create(['branch_id' => $branch->id, 'name' => 'Phòng Nhân sự', 'code' => 'HR']);
        $pos = Position::create(['department_id' => $dept->id, 'name' => 'Chuyên viên Tuyển dụng', 'code' => 'REC']);

        $employee = Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'first_name' => 'An',
            'last_name' => 'Tran',
            'full_name' => 'Tran Van An',
            'employee_code' => 'NV-HR-02',
            'email' => 'hr_staff@example.com',
            'employment_status' => 'active',
            'is_active' => true,
        ]);

        return compact('company', 'branch', 'dept', 'pos', 'employee');
    }

    public function test_admin_can_perform_award_discipline_crud(): void
    {
        $user = $this->createAdminUser();
        $setup = $this->setupEmployee();
        $employee = $setup['employee'];

        $headers = ['Authorization' => 'Bearer ' . $user->api_token];

        // 1. Test POST /api/v1/employees/{id}/awards-discipline
        $postData = [
            'type' => 'award',
            'decision_number' => 'QĐ-11/KT',
            'decision_date' => '2026-05-20',
            'reason' => 'Thành tích xuất sắc dự án ERP',
            'amount' => 5000000.00,
            'signed_by' => 'CEO Nguyen Van A',
            'note' => 'Thưởng nóng bằng tiền mặt',
        ];

        $response = $this->withHeaders($headers)
            ->postJson("/api/v1/employees/{$employee->id}/awards-discipline", $postData);
        
        $response->assertCreated();
        $response->assertJsonPath('data.decision_number', 'QĐ-11/KT');
        $awardId = $response->json('data.id');

        // 2. Test GET /api/v1/employees/{id}/awards-discipline
        $getResponse = $this->withHeaders($headers)
            ->getJson("/api/v1/employees/{$employee->id}/awards-discipline");
        
        $getResponse->assertOk();
        $getResponse->assertJsonCount(1, 'data');

        // 3. Test DELETE /api/v1/employees/{id}/awards-discipline/{awardId}
        $deleteResponse = $this->withHeaders($headers)
            ->deleteJson("/api/v1/employees/{$employee->id}/awards-discipline/{$awardId}");
        
        $deleteResponse->assertNoContent();
        $this->assertDatabaseMissing('employee_awards_disciplines', ['id' => $awardId]);
    }

    public function test_admin_can_create_transfer_and_approve_it_syncing_employee_profile(): void
    {
        $user = $this->createAdminUser();
        $setup = $this->setupEmployee();
        $employee = $setup['employee'];
        $company = $setup['company'];
        $branch = $setup['branch'];
        $dept = $setup['dept'];

        // Tạo chi nhánh/phòng/chức vụ mới để điều động đến
        $branchNew = Branch::create(['company_id' => $company->id, 'name' => 'Chi nhánh HCM', 'code' => 'CNHCM']);
        $deptNew = Department::create(['branch_id' => $branchNew->id, 'name' => 'Phòng Kinh doanh', 'code' => 'SALES']);
        $posNew = Position::create(['department_id' => $deptNew->id, 'name' => 'Trưởng phòng Kinh doanh', 'code' => 'SALES_MGR']);

        $headers = ['Authorization' => 'Bearer ' . $user->api_token];

        // 1. Test POST /api/v1/employees/{id}/transfers (Tạo đơn điều động - Bổ nhiệm làm Trưởng phòng)
        $postData = [
            'decision_number' => 'QĐ-12/BNN',
            'effective_date' => '2026-06-01',
            'type' => 'promotion',
            'reason' => 'Thăng chức Trưởng phòng Kinh doanh',
            'signed_by' => 'CEO Nguyen Van A',
            'to_branch_id' => $branchNew->id,
            'to_department_id' => $deptNew->id,
            'to_position_id' => $posNew->id,
        ];

        $response = $this->withHeaders($headers)
            ->postJson("/api/v1/employees/{$employee->id}/transfers", $postData);
        
        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
        $transferId = $response->json('data.id');

        // 2. Test POST /api/v1/employees/{id}/transfers/{transferId}/approve (Duyệt đơn)
        $approveResponse = $this->withHeaders($headers)
            ->postJson("/api/v1/employees/{$employee->id}/transfers/{$transferId}/approve");
        
        $approveResponse->assertOk();
        $approveResponse->assertJsonPath('data.status', 'approved');

        // 3. Xác minh hồ sơ nhân viên đã được tự động cập nhật
        $employee->refresh();
        $this->assertEquals($branchNew->id, $employee->branch_id);
        $this->assertEquals($deptNew->id, $employee->department_id);
        $this->assertEquals($posNew->id, $employee->position_id);
    }

    public function test_admin_can_create_termination_and_approve_it_syncing_employee_profile(): void
    {
        $user = $this->createAdminUser();
        $setup = $this->setupEmployee();
        $employee = $setup['employee'];

        $headers = ['Authorization' => 'Bearer ' . $user->api_token];

        // 1. Test POST /api/v1/employees/{id}/terminations
        $postData = [
            'decision_number' => 'QĐ-13/TV',
            'termination_date' => '2026-05-31',
            'reason' => 'Xin thôi việc theo nguyện vọng cá nhân',
            'type' => 'resignation',
            'signed_by' => 'HR Director',
        ];

        $response = $this->withHeaders($headers)
            ->postJson("/api/v1/employees/{$employee->id}/terminations", $postData);
        
        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
        $terminationId = $response->json('data.id');

        // 2. Test POST /api/v1/employees/{id}/terminations/{terminationId}/approve (Duyệt đơn thôi việc)
        $approveResponse = $this->withHeaders($headers)
            ->postJson("/api/v1/employees/{$employee->id}/terminations/{$terminationId}/approve");
        
        $approveResponse->assertOk();
        $approveResponse->assertJsonPath('data.status', 'approved');

        // 3. Xác minh nhân sự đã bị ngừng kích hoạt và đổi trạng thái sang terminated
        $employee->refresh();
        $this->assertEquals('terminated', $employee->employment_status);
        $this->assertEquals('2026-05-31', $employee->termination_date->format('Y-m-d'));
        $this->assertEquals('Xin thôi việc theo nguyện vọng cá nhân', $employee->termination_reason);
        $this->assertFalse($employee->is_active);
    }

    public function test_admin_can_fetch_hr_overview_report(): void
    {
        $user = $this->createAdminUser();
        $setup = $this->setupEmployee();
        $employee = $setup['employee'];
        $company = $setup['company'];

        // Thêm một quyết định khen thưởng cho năm nay để báo cáo có số liệu
        EmployeeAwardDiscipline::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'award',
            'decision_number' => 'QĐ-01/KT',
            'decision_date' => now()->format('Y-m-d'),
            'reason' => 'Thành tích xuất sắc',
        ]);

        $headers = ['Authorization' => 'Bearer ' . $user->api_token];

        $response = $this->withHeaders($headers)
            ->getJson("/api/v1/reports/hr-overview?company_id={$company->id}");
        
        $response->assertOk();
        $response->assertJsonPath('data.summary.total_active', 1);
        $response->assertJsonPath('data.summary.awards_this_year', 1);
        $response->assertJsonCount(1, 'data.departments');
    }
}
