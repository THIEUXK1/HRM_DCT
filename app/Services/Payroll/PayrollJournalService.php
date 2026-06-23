<?php

namespace App\Services\Payroll;

use App\Models\Department;
use App\Models\PayrollCycle;
use App\Models\PayrollJournalEntry;
use App\Models\PayrollJournalMapping;
use Illuminate\Support\Facades\DB;

class PayrollJournalService
{
    /**
     * Tạo bút toán nhật ký cho một kỳ lương cụ thể.
     */
    public function generateForCycle(PayrollCycle $cycle): ?PayrollJournalEntry
    {
        // Xóa bút toán nháp hiện tại của kỳ này
        PayrollJournalEntry::where('payroll_cycle_id', $cycle->id)
            ->where('status', 'draft')
            ->delete();

        $results = $cycle->results()->with('employee')->get();
        if ($results->isEmpty()) {
            return null;
        }

        $period = $cycle->period;
        $year = (int) explode('-', $period)[0];
        // FDI TT 99/2025 áp dụng cho 2026 trở đi, còn lại TT200/2014
        $accountingRegime = $year >= 2026 ? 'TT99_2025' : 'TT200_2014';

        $entry = PayrollJournalEntry::create([
            'company_id' => $cycle->company_id,
            'payroll_cycle_id' => $cycle->id,
            'reference_number' => 'JV-PR-' . $cycle->id . '-' . time(),
            'description' => 'Hạch toán chi phí lương & bảo hiểm kỳ ' . $period . ' (Run ' . $cycle->run_number . ')',
            'entry_date' => $cycle->end_date ?? now()->toDateString(),
            'accounting_regime' => $accountingRegime,
            'status' => 'draft',
        ]);

        $lines = [];

        foreach ($results as $res) {
            $emp = $res->employee;
            if (! $emp) {
                continue;
            }
            $deptId = $emp->department_id;
            $breakdown = $res->breakdown ?? [];

            // 1. Phải trả người lao động (LCB + OT + Phụ cấp)
            // Nợ TK 622/627/641/642, Có TK 334
            $gross = (float) $res->gross_salary;
            if ($gross > 0) {
                $debit = $this->resolveAccount($cycle->company_id, 'salary', $deptId, 'debit');
                $credit = $this->resolveAccount($cycle->company_id, 'salary', $deptId, 'credit');
                $lines[] = [
                    'debit_account' => $debit,
                    'credit_account' => $credit,
                    'amount' => $gross,
                    'description' => "Lương và phụ cấp phải trả - " . $emp->full_name,
                    'employee_id' => $emp->id,
                    'department_id' => $deptId,
                ];
            }

            // 2. Bảo hiểm trừ lương Người lao động (BHXH 8%, BHYT 1.5%, BHTN 1.0%)
            // Nợ TK 334, Có TK 3383 / 3384 / 3386
            $bhxhEmp = (float) ($breakdown['bhxh_employee_detail'] ?? 0);
            $bhytEmp = (float) ($breakdown['bhyt_employee_detail'] ?? 0);
            $bhtnEmp = (float) ($breakdown['bhtn_employee_detail'] ?? 0);

            if ($bhxhEmp > 0) {
                $debit = $this->resolveAccount($cycle->company_id, 'employee_insurance_bhxh', $deptId, 'debit');
                $credit = $this->resolveAccount($cycle->company_id, 'employee_insurance_bhxh', $deptId, 'credit');
                $lines[] = [
                    'debit_account' => $debit,
                    'credit_account' => $credit,
                    'amount' => $bhxhEmp,
                    'description' => "Trích đóng BHXH (8% NLĐ) - " . $emp->full_name,
                    'employee_id' => $emp->id,
                    'department_id' => $deptId,
                ];
            }

            if ($bhytEmp > 0) {
                $debit = $this->resolveAccount($cycle->company_id, 'employee_insurance_bhyt', $deptId, 'debit');
                $credit = $this->resolveAccount($cycle->company_id, 'employee_insurance_bhyt', $deptId, 'credit');
                $lines[] = [
                    'debit_account' => $debit,
                    'credit_account' => $credit,
                    'amount' => $bhytEmp,
                    'description' => "Trích đóng BHYT (1.5% NLĐ) - " . $emp->full_name,
                    'employee_id' => $emp->id,
                    'department_id' => $deptId,
                ];
            }

            if ($bhtnEmp > 0) {
                $debit = $this->resolveAccount($cycle->company_id, 'employee_insurance_bhtn', $deptId, 'debit');
                $credit = $this->resolveAccount($cycle->company_id, 'employee_insurance_bhtn', $deptId, 'credit');
                $lines[] = [
                    'debit_account' => $debit,
                    'credit_account' => $credit,
                    'amount' => $bhtnEmp,
                    'description' => "Trích đóng BHTN (1% NLĐ) - " . $emp->full_name,
                    'employee_id' => $emp->id,
                    'department_id' => $deptId,
                ];
            }

            // 3. Bảo hiểm doanh nghiệp đóng (BHXH 17.5%, BHYT 3.0%, BHTN 1.0%)
            // Nợ TK 622/627/641/642, Có TK 3383 / 3384 / 3386
            $bhxhEr = (float) ($breakdown['bhxh_employer_detail'] ?? 0);
            $bhytEr = (float) ($breakdown['bhyt_employer_detail'] ?? 0);
            $bhtnEr = (float) ($breakdown['bhtn_employer_detail'] ?? 0);

            if ($bhxhEr > 0) {
                $debit = $this->resolveAccount($cycle->company_id, 'employer_insurance_bhxh', $deptId, 'debit');
                $credit = $this->resolveAccount($cycle->company_id, 'employer_insurance_bhxh', $deptId, 'credit');
                $lines[] = [
                    'debit_account' => $debit,
                    'credit_account' => $credit,
                    'amount' => $bhxhEr,
                    'description' => "BHXH doanh nghiệp chịu (17.5%) - " . $emp->full_name,
                    'employee_id' => $emp->id,
                    'department_id' => $deptId,
                ];
            }

            if ($bhytEr > 0) {
                $debit = $this->resolveAccount($cycle->company_id, 'employer_insurance_bhyt', $deptId, 'debit');
                $credit = $this->resolveAccount($cycle->company_id, 'employer_insurance_bhyt', $deptId, 'credit');
                $lines[] = [
                    'debit_account' => $debit,
                    'credit_account' => $credit,
                    'amount' => $bhytEr,
                    'description' => "BHYT doanh nghiệp chịu (3%) - " . $emp->full_name,
                    'employee_id' => $emp->id,
                    'department_id' => $deptId,
                ];
            }

            if ($bhtnEr > 0) {
                $debit = $this->resolveAccount($cycle->company_id, 'employer_insurance_bhtn', $deptId, 'debit');
                $credit = $this->resolveAccount($cycle->company_id, 'employer_insurance_bhtn', $deptId, 'credit');
                $lines[] = [
                    'debit_account' => $debit,
                    'credit_account' => $credit,
                    'amount' => $bhtnEr,
                    'description' => "BHTN doanh nghiệp chịu (1%) - " . $emp->full_name,
                    'employee_id' => $emp->id,
                    'department_id' => $deptId,
                ];
            }

            // 4. Kinh phí công đoàn 2% doanh nghiệp chịu
            // Nợ TK 622/627/641/642, Có TK 3382
            $kpcdEr = (float) ($breakdown['kpcd_employer_detail'] ?? 0);
            if ($kpcdEr > 0) {
                $debit = $this->resolveAccount($cycle->company_id, 'kpcd', $deptId, 'debit');
                $credit = $this->resolveAccount($cycle->company_id, 'kpcd', $deptId, 'credit');
                $lines[] = [
                    'debit_account' => $debit,
                    'credit_account' => $credit,
                    'amount' => $kpcdEr,
                    'description' => "KPCĐ doanh nghiệp chịu (2%) - " . $emp->full_name,
                    'employee_id' => $emp->id,
                    'department_id' => $deptId,
                ];
            }

            // 5. Đoàn phí công đoàn viên 1% trừ lương người lao động
            // Nợ TK 334, Có TK 3388 (hoặc TK nội bộ được mapping)
            $unionFee = (float) ($breakdown['union_fee'] ?? 0);
            if ($unionFee > 0) {
                $debit = $this->resolveAccount($cycle->company_id, 'union_fee', $deptId, 'debit');
                $credit = $this->resolveAccount($cycle->company_id, 'union_fee', $deptId, 'credit');
                $lines[] = [
                    'debit_account' => $debit,
                    'credit_account' => $credit,
                    'amount' => $unionFee,
                    'description' => "Khấu trừ đoàn phí công đoàn viên (1%) - " . $emp->full_name,
                    'employee_id' => $emp->id,
                    'department_id' => $deptId,
                ];
            }
        }

        // Lưu toàn bộ chi tiết bút toán trong một Transaction
        DB::transaction(function () use ($entry, $lines) {
            foreach ($lines as $line) {
                $entry->lines()->create($line);
            }
        });

        return $entry;
    }

    /**
     * Giải quyết tài khoản hạch toán dựa trên mapping cấu hình hoặc các fallback mặc định.
     */
    private function resolveAccount(int $companyId, string $type, ?int $departmentId, string $direction = 'debit'): string
    {
        // 1. Kiểm tra cấu hình cụ thể theo phòng ban
        if ($departmentId) {
            $mapping = PayrollJournalMapping::where('company_id', $companyId)
                ->where('mapping_type', $type)
                ->where('department_id', $departmentId)
                ->first();
            if ($mapping) {
                return $direction === 'debit' ? $mapping->debit_account : $mapping->credit_account;
            }
        }

        // 2. Kiểm tra cấu hình mặc định (department_id là null)
        $defaultMapping = PayrollJournalMapping::where('company_id', $companyId)
            ->where('mapping_type', $type)
            ->whereNull('department_id')
            ->first();
        if ($defaultMapping) {
            return $direction === 'debit' ? $defaultMapping->debit_account : $defaultMapping->credit_account;
        }

        // 3. Fallback mặc định theo chế độ kế toán
        if ($direction === 'credit') {
            return match ($type) {
                'salary' => '334',
                'employee_insurance_bhxh', 'employer_insurance_bhxh' => '3383',
                'employee_insurance_bhyt', 'employer_insurance_bhyt' => '3384',
                'employee_insurance_bhtn', 'employer_insurance_bhtn' => '3386',
                'kpcd' => '3382',
                'union_fee' => '3388', // Dùng 3388 làm trần đề xuất đoàn phí cho đến khi KTT xác nhận
                default => '3388'
            };
        }

        // Trích bảo hiểm & đoàn phí bên Nợ đều thông qua TK 334 để trừ Net NLĐ
        if (in_array($type, ['employee_insurance_bhxh', 'employee_insurance_bhyt', 'employee_insurance_bhtn', 'union_fee'])) {
            return '334';
        }

        // Với Lương, BH doanh nghiệp chịu, KPCĐ: giải quyết nợ chi phí theo phòng ban (622/627/641/642)
        return $this->resolveExpenseAccount($departmentId);
    }

    private function resolveExpenseAccount(?int $departmentId): string
    {
        if (! $departmentId) {
            return '642'; // Quản lý doanh nghiệp
        }

        $dept = Department::find($departmentId);
        if (! $dept) {
            return '642';
        }

        $name = mb_strtolower($dept->name, 'UTF-8');

        // Tìm từ khóa để đoán TK chi phí lương bộ phận
        if (str_contains($name, 'sản xuất') || str_contains($name, 'trực tiếp') || str_contains($name, 'sản phẩm') || str_contains($name, 'nhà máy') || str_contains($name, 'phân xưởng')) {
            return '622'; // Chi phí nhân công trực tiếp
        }
        if (str_contains($name, 'bán hàng') || str_contains($name, 'kinh doanh') || str_contains($name, 'marketing') || str_contains($name, 'sales')) {
            return '641'; // Chi phí bán hàng
        }
        if (str_contains($name, 'kỹ thuật') || str_contains($name, 'bảo dưỡng') || str_contains($name, 'kho') || str_contains($name, 'phân xưởng phụ') || str_contains($name, 'quản lý phân xưởng')) {
            return '627'; // Chi phí sản xuất chung
        }

        return '642'; // Chi phí quản lý doanh nghiệp
    }
}
