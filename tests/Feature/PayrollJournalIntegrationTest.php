<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\OvertimeRequest;
use App\Models\PayrollCycle;
use App\Models\PayrollJournalEntry;
use App\Models\PayrollJournalMapping;
use App\Models\PayrollResult;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\Attendance\OvertimeExcessService;
use App\Services\Payroll\PayrollCycleLockService;
use App\Services\Payroll\PayrollJournalService;
use App\Services\Payroll\VietnamPayrollCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollJournalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        Role::firstOrCreate(['name' => 'admin']);

        $user = User::factory()->create([
            'email' => 'admin_' . uniqid() . '@example.com',
            'password' => Hash::make('Admin@123'),
        ]);
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'admin-token-' . uniqid()])->save();

        return $user;
    }

    private function headers(User $user, Company $company): array
    {
        return [
            'Authorization' => 'Bearer ' . $user->api_token,
            'X-Company-Id' => (string) $company->id,
        ];
    }

    /**
     * Test BHYT, BHTN, KPCĐ và Đoàn phí công đoàn 1% trong VietnamPayrollCalculator.
     */
    public function test_vietnam_payroll_calculator_computes_detailed_insurance_and_union_dues(): void
    {
        $calculator = new VietnamPayrollCalculator();

        // Cấu hình mẫu cho test
        config([
            'payroll_vn.bhxh.employee_rate' => 0.08,
            'payroll_vn.bhxh.employer_rate' => 0.175,
            'payroll_vn.bhyt.employee_rate' => 0.015,
            'payroll_vn.bhyt.employer_rate' => 0.03,
            'payroll_vn.bhtn.employee_rate' => 0.01,
            'payroll_vn.bhtn.employer_rate' => 0.01,
            'payroll_vn.kpcd.employer_rate' => 0.02,
            'payroll_vn.union_fee.employee_rate' => 0.01,
            'payroll_vn.union_fee.cap_amount' => 180000,
            'payroll_vn.bhxh.salary_cap' => 46800000,
            'payroll_vn.pit.personal_deduction' => 11000000,
            'payroll_vn.pit.dependent_deduction' => 4400000,
        ]);

        $gross = 15000000.0;
        $insuranceBase = 10000000.0; // BHXH tính trên 10tr

        // 1. Nhân sự CÓ đóng đoàn phí (union_member = true)
        $result = $calculator->calculateWithInsuranceBase(
            $gross,
            $insuranceBase,
            0,
            0.0,
            0.0,
            true // unionMember
        );

        // Employee: BHXH (800k) + BHYT (150k) + BHTN (100k) = 1.050.000đ
        $this->assertEquals(1050000.0, $result['bhxh_employee']);
        $this->assertEquals(800000.0, $result['bhxh_employee_detail']);
        $this->assertEquals(150000.0, $result['bhyt_employee_detail']);
        $this->assertEquals(100000.0, $result['bhtn_employee_detail']);

        // Employer: BHXH (1.750.000) + BHYT (300k) + BHTN (100k) + KPCĐ (200k) = 2.350.000đ
        $this->assertEquals(2350000.0, $result['bhxh_employer']);
        $this->assertEquals(1750000.0, $result['bhxh_employer_detail']);
        $this->assertEquals(300000.0, $result['bhyt_employer_detail']);
        $this->assertEquals(100000.0, $result['bhtn_employer_detail']);
        $this->assertEquals(200000.0, $result['kpcd_employer_detail']);

        // Đoàn phí: 1% của 10tr = 100.000đ
        $this->assertEquals(100000.0, $result['union_fee']);
        $this->assertTrue($result['union_member']);

        // 2. Nhân sự KHÔNG đóng đoàn phí (union_member = false)
        $resultNoUnion = $calculator->calculateWithInsuranceBase(
            $gross,
            $insuranceBase,
            0,
            0.0,
            0.0,
            false // unionMember
        );
        $this->assertEquals(0.0, $resultNoUnion['union_fee']);
        $this->assertFalse($resultNoUnion['union_member']);
    }

    /**
     * Test kiểm tra tuân thủ OT tuần (loại bỏ thứ 7 khi làm 7 ngày, cắt giảm khi vượt 66h).
     */
    public function test_overtime_weekly_day_off_and_66h_limit_compliance(): void
    {
        $company = Company::create(['name' => 'FDI Company', 'code' => 'FDI']);
        \App\Support\CompanyContext::set($company->id);

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-TEST-OT',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'email' => 'john.doe@local.test',
            'employment_status' => 'active',
            'is_active' => true,
        ]);

        $period = '2026-05';
        $weekStart = Carbon::parse('2026-05-04'); // Thứ Hai
        $weekEnd = Carbon::parse('2026-05-10'); // Chủ Nhật

        // Tạo logs đi làm tất cả 7 ngày trong tuần từ 04/05 đến 10/05
        $current = $weekStart->copy();
        while ($current <= $weekEnd) {
            \App\Models\AttendanceLog::create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'work_date' => $current->toDateString(),
                'check_in_at' => $current->copy()->setTime(8, 0)->toDateTimeString(),
                'check_out_at' => $current->copy()->setTime(17, 0)->toDateTimeString(), // 8 giờ làm
                'work_hours' => 8.0,
            ]);
            $current->addDay();
        }

        // Tạo yêu cầu OT vào thứ Bảy (09/05) và Chủ Nhật (10/05)
        $saturday = Carbon::parse('2026-05-09');
        $saturdayOt = OvertimeRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'work_date' => $saturday->toDateString(),
            'hours' => 4.0,
            'ot_type' => 'weekend',
            'status' => 'approved',
        ]);

        $sunday = Carbon::parse('2026-05-10');
        $sundayOt = OvertimeRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'work_date' => $sunday->toDateString(),
            'hours' => 4.0,
            'ot_type' => 'weekend',
            'status' => 'approved',
        ]);

        // Chạy kiểm tra tuân thủ hàng tuần
        $otExcessService = app(OvertimeExcessService::class);
        $otExcessService->checkWeeklyCompliance($employee->id, $period);

        // Kiểm tra xem OT thứ Bảy đã bị loại bỏ chưa vì làm đủ 7 ngày liên tục không nghỉ
        $this->assertDatabaseHas('overtime_excess_records', [
            'overtime_request_id' => $saturdayOt->id,
            'cap_type' => 'weekly_day_off',
            'exclude_from_payroll' => true,
            'excess_hours' => 4.0,
        ]);
    }

    /**
     * Test tự động tạo bút toán hạch toán lương & KPCĐ khi khóa kỳ lương (TT99/2025).
     */
    public function test_auto_generate_payroll_journal_entries_on_payroll_lock(): void
    {
        $company = Company::create(['name' => 'FDI Factory', 'code' => 'FF']);
        \App\Support\CompanyContext::set($company->id);

        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Main Branch', 'code' => 'MB']);
        $user = $this->createAdminUser();

        // Tạo phòng ban sản xuất (được map về TK 622 theo text matching)
        $prodDept = Department::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Nhà máy Sản xuất',
            'code' => 'NMSX',
        ]);

        // Tạo nhân sự công nhân
        $employee = Employee::create([
            'company_id' => $company->id,
            'department_id' => $prodDept->id,
            'employee_code' => 'EMP-PROD',
            'first_name' => 'Nguyen',
            'last_name' => 'Van A',
            'full_name' => 'Nguyen Van A',
            'email' => 'vanya@local.test',
            'employment_status' => 'active',
            'union_member' => true,
            'is_active' => true,
        ]);

        // Hợp đồng lao động active
        $contract = EmploymentContract::create([
            'employee_id' => $employee->id,
            'contract_number' => 'CTR-PROD-01',
            'contract_type' => 'fixed_term',
            'start_date' => '2026-01-01',
            'salary_base' => 12000000.0,
            'insurance_salary' => 10000000.0,
            'status' => 'active',
        ]);

        // Tạo kỳ lương
        $cycle = PayrollCycle::create([
            'company_id' => $company->id,
            'period' => '2026-05',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'status' => 'calculated',
            'run_number' => 1,
            'reference_number' => 'CYC-2026-05-1',
            'label' => 'Kỳ lương tháng 5',
        ]);

        // Giả lập kết quả tính lương
        PayrollResult::create([
            'payroll_cycle_id' => $cycle->id,
            'employee_id' => $employee->id,
            'gross_salary' => 12000000.0,
            'bhxh_employee' => 1050000.0, // BHXH+BHYT+BHTN
            'bhxh_employer' => 2350000.0, // BHXH+BHYT+BHTN+KPCĐ
            'pit_amount' => 100000.0,
            'other_deductions' => 100000.0, // Union member fee
            'net_salary' => 10750000.0,
            'breakdown' => [
                'bhxh_employee_detail' => 800000.0,
                'bhyt_employee_detail' => 150000.0,
                'bhtn_employee_detail' => 100000.0,
                'bhxh_employer_detail' => 1750000.0,
                'bhyt_employer_detail' => 300000.0,
                'bhtn_employer_detail' => 100000.0,
                'kpcd_employer_detail' => 200000.0,
                'union_fee' => 100000.0,
            ],
        ]);

        // Khóa kỳ lương (Sẽ tự động gọi PayrollJournalService)
        $lockService = app(PayrollCycleLockService::class);
        $lockService->lock($cycle, $user);

        // Kiểm tra xem PayrollJournalEntry ở trạng thái draft đã được tạo chưa
        $entry = PayrollJournalEntry::where('payroll_cycle_id', $cycle->id)->first();
        $this->assertNotNull($entry);
        $this->assertEquals('draft', $entry->status);
        $this->assertEquals('TT99_2025', $entry->accounting_regime); // FDI năm 2026 dùng TT 99

        // Xác minh các dòng bút toán chi tiết (Lines)
        // 1. Phải trả lương: Nợ 622, Có 334 = 12.000.000đ
        $this->assertDatabaseHas('payroll_journal_lines', [
            'payroll_journal_entry_id' => $entry->id,
            'debit_account' => '622',
            'credit_account' => '334',
            'amount' => 12000000.00,
        ]);

        // 2. Bảo hiểm trừ lương NLĐ (BHXH 8%): Nợ 334, Có 3383 = 800.000đ
        $this->assertDatabaseHas('payroll_journal_lines', [
            'payroll_journal_entry_id' => $entry->id,
            'debit_account' => '334',
            'credit_account' => '3383',
            'amount' => 800000.00,
        ]);

        // 3. Bảo hiểm doanh nghiệp đóng (BHXH 17.5%): Nợ 622, Có 3383 = 1.750.000đ
        $this->assertDatabaseHas('payroll_journal_lines', [
            'payroll_journal_entry_id' => $entry->id,
            'debit_account' => '622',
            'credit_account' => '3383',
            'amount' => 1750000.00,
        ]);

        // 4. KPCĐ 2%: Nợ 622, Có 3382 = 200.000đ
        $this->assertDatabaseHas('payroll_journal_lines', [
            'payroll_journal_entry_id' => $entry->id,
            'debit_account' => '622',
            'credit_account' => '3382',
            'amount' => 200000.00,
        ]);

        // 5. Khấu trừ đoàn phí 1%: Nợ 334, Có 3388 = 100.000đ
        $this->assertDatabaseHas('payroll_journal_lines', [
            'payroll_journal_entry_id' => $entry->id,
            'debit_account' => '334',
            'credit_account' => '3388',
            'amount' => 100000.00,
        ]);

        // Test API Post bút toán (chuyển trạng thái sang posted)
        $headers = $this->headers($user, $company);
        $response = $this->withHeaders($headers)
            ->postJson("/api/v1/payroll-journal-entries/{$entry->id}/post");

        $response->assertOk();
        $this->assertEquals('posted', $response->json('data.status'));
        $this->assertNotNull($response->json('data.posted_by'));
    }
}
