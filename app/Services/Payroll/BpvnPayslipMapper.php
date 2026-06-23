<?php

namespace App\Services\Payroll;

use App\Models\PayrollResult;
use Carbon\Carbon;

/**
 * Map payroll_results.breakdown → 46 dòng mẫu BPVN-AC-PR-006 (Phase 1).
 */
class BpvnPayslipMapper
{
    /** @var array<string, array<string, mixed>> */
    private array $lineValues = [];

    public function map(PayrollResult $result): array
    {
        $result->loadMissing([
            'employee.company',
            'employee.department',
            'employee.position',
            'cycle',
            'payslip',
        ]);

        $employee = $result->employee;
        $breakdown = is_array($result->breakdown) ? $result->breakdown : [];
        $period = $result->cycle?->period ?? now()->format('Y-m');
        $periodDate = Carbon::createFromFormat('Y-m', $period);

        $this->lineValues = $this->buildLineValues($result, $breakdown, $employee);

        $allowanceTotal = $this->sumSttRange(['10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '22a']);
        $this->lineValues['23'] = ['type' => 'money', 'amount' => $allowanceTotal];

        $otPay = (float) ($breakdown['ot_pay'] ?? 0);
        $dayPay = (float) ($breakdown['ot_day_pay'] ?? 0);
        $nightPay = (float) ($breakdown['ot_night_pay'] ?? 0);

        if ($dayPay <= 0 && $nightPay <= 0 && $otPay > 0) {
            $dayPay = $otPay;
        }

        $this->lineValues['29'] = ['type' => 'money', 'amount' => $dayPay];
        $this->lineValues['34'] = ['type' => 'money', 'amount' => $nightPay];
        $this->lineValues['35'] = ['type' => 'money', 'amount' => $otPay];

        $totalDeductions = (float) $result->bhxh_employee
            + (float) $result->pit_amount
            + (float) $result->other_deductions;
        $this->lineValues['42'] = ['type' => 'money', 'amount' => $totalDeductions];

        $company = $employee->company;

        return [
            'result' => $result,
            'meta' => [
                'company_name_vi' => $company?->name ?? '',
                'doc_code' => config('payslip_templates.templates.bpvn-ac-pr-006.doc_code', 'BPVN-AC-PR-006 A/1'),
                'period' => $period,
                'period_month' => (int) $periodDate->format('m'),
                'period_year' => (int) $periodDate->format('Y'),
                'published_at' => optional($result->payslip?->published_at)->format('d/m/Y H:i'),
            ],
            'lines' => $this->buildDisplayLines(),
            'notes' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $breakdown
     * @return array<string, array<string, mixed>>
     */
    private function buildLineValues(PayrollResult $result, array $breakdown, $employee): array
    {
        $lines = [];
        $attendance = is_array($breakdown['attendance_breakdown'] ?? null) ? $breakdown['attendance_breakdown'] : [];
        $otGrid = $attendance['ot'] ?? $breakdown['ot_grid'] ?? [];
        $leaveByType = $attendance['leave_by_type'] ?? $breakdown['leave_by_type'] ?? [];
        $workMeta = $attendance['work'] ?? [];
        $pa = is_array($breakdown['payslip_attendance'] ?? null) ? $breakdown['payslip_attendance'] : [];

        $standardDays = (float) ($pa['standard_work_days'] ?? $breakdown['standard_work_days'] ?? $attendance['meta']['standard_work_days'] ?? 0);
        $workDays = (float) ($pa['work_days'] ?? $breakdown['work_days'] ?? $workMeta['payable_work_days'] ?? 0);
        $payableProbation = (float) ($pa['payable_probation_days'] ?? $breakdown['payable_probation_days'] ?? 0);
        $payableOfficial = (float) ($pa['payable_official_days'] ?? $breakdown['payable_official_days'] ?? 0);
        $payableTotal = (float) ($pa['payable_total_days'] ?? ($payableProbation + $payableOfficial));
        if ($payableTotal <= 0 && $workDays > 0) {
            $payableTotal = $workDays + (float) ($pa['paid_leave_days'] ?? $breakdown['paid_leave_days'] ?? 0);
        }

        $lines['1'] = ['type' => 'text', 'text' => (string) ($employee->employee_code ?? '')];
        $lines['2'] = ['type' => 'text', 'text' => (string) ($employee->full_name ?? '')];
        $lines['3'] = ['type' => 'text', 'text' => (string) ($employee->department?->name ?? '')];
        $lines['3b'] = ['type' => 'text', 'text' => (string) ($employee->position?->level ?? $employee->position?->name ?? '')];

        $lines['4'] = ['type' => 'money', 'amount' => (float) ($breakdown['base_salary_monthly'] ?? 0)];
        $lines['6'] = [
            'type' => 'text',
            'text' => $this->formatWorkDaysLine($workDays, $standardDays, $payableTotal, $payableProbation, $payableOfficial, (bool) ($pa['has_phase_split'] ?? $breakdown['has_phase_split'] ?? false)),
        ];
        $lines['7'] = ['type' => 'days', 'amount' => (float) ($pa['paid_leave_days'] ?? $breakdown['paid_leave_days'] ?? 0)];
        $lines['8'] = ['type' => 'days', 'amount' => (float) ($pa['unpaid_leave_days'] ?? $breakdown['unpaid_leave_days'] ?? 0)];
        $lines['8a'] = ['type' => 'days', 'amount' => (float) ($workMeta['minimum_wage_days'] ?? $breakdown['minimum_wage_days'] ?? 0)];
        $lines['9'] = ['type' => 'money', 'amount' => (float) ($breakdown['base_pay_total'] ?? 0)];

        $allowanceStts = ['10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '22a'];
        foreach ($allowanceStts as $stt) {
            $lines[$stt] = ['type' => 'money', 'amount' => 0.0];
        }

        $this->applyFieldMaps($lines, $breakdown);
        $this->applyFormulaLines($lines, $breakdown, $allowanceStts);

        $lines['24'] = ['type' => 'money', 'amount' => (float) ($breakdown['travel_support'] ?? $lines['24']['amount'] ?? 0)];

        foreach (['25', '26', '27', '28', '30', '31', '32', '33'] as $stt) {
            $lines[$stt] = ['type' => 'hours', 'amount' => 0.0];
        }
        foreach (config('attendance_vn.payslip_ot_hour_map', []) as $otKey => $stt) {
            $lines[$stt]['amount'] = ($lines[$stt]['amount'] ?? 0) + (float) ($otGrid[$otKey] ?? 0);
        }

        foreach (config('attendance_vn.payslip_leave_day_map', []) as $leaveKey => $stt) {
            $lines[$stt] = ['type' => 'days', 'amount' => (float) ($leaveByType[$leaveKey] ?? 0)];
        }

        $lines['34'] = ['type' => 'money', 'amount' => (float) ($breakdown['ot_night_pay'] ?? 0)];
        $lines['36'] = ['type' => 'hours', 'amount' => (float) ($breakdown['night_hours'] ?? 0)];
        $lines['37'] = ['type' => 'money', 'amount' => 0.0];
        $lines['37a'] = ['type' => 'hours', 'amount' => (float) ($workMeta['menstrual_leave_hours'] ?? $breakdown['menstrual_leave_hours'] ?? 0)];
        $lines['37b'] = ['type' => 'money', 'amount' => 0.0];

        $lines['35a'] = ['type' => 'money', 'amount' => (float) ($breakdown['incentive_bonus'] ?? 0) + (float) ($breakdown['diligence_bonus_pay'] ?? 0)];
        $lines['38'] = ['type' => 'money', 'amount' => (float) $result->gross_salary];
        $lines['39'] = ['type' => 'money', 'amount' => (float) $result->bhxh_employee];
        $lines['40'] = ['type' => 'money', 'amount' => (float) $result->pit_amount];
        $lines['41'] = ['type' => 'money', 'amount' => (float) $result->other_deductions];
        $lines['43'] = ['type' => 'money', 'amount' => (float) ($breakdown['termination_leave_payout'] ?? $breakdown['prev_month_adjustment'] ?? $lines['43']['amount'] ?? 0)];
        $lines['44'] = ['type' => 'money', 'amount' => (float) ($breakdown['performance_bonus'] ?? $lines['44']['amount'] ?? 0)];
        $lines['44a'] = ['type' => 'money', 'amount' => (float) ($breakdown['lunch_allowance'] ?? $lines['44a']['amount'] ?? 0)];
        $lines['46'] = ['type' => 'money', 'amount' => (float) $result->net_salary];

        return $lines;
    }

    /** @param  array<string, array<string, mixed>>  $lines */
    private function applyFieldMaps(array &$lines, array $breakdown): void
    {
        $maps = array_merge(
            config('payslip_templates.allowance_field_map', []),
            config('payslip_templates.special_field_map', []),
        );

        foreach ($maps as $field => $stt) {
            if (! array_key_exists($field, $breakdown)) {
                continue;
            }
            $amount = (float) $breakdown[$field];
            if ($amount == 0.0 && empty($lines[$stt]['amount'])) {
                continue;
            }
            $lines[$stt] = ['type' => 'money', 'amount' => $amount];
        }
    }

    /**
     * Gán formula_lines chưa map vào slot trợ cấp còn trống (10–22).
     *
     * @param  array<string, array<string, mixed>>  $lines
     * @param  list<string>  $allowanceStts
     */
    private function applyFormulaLines(array &$lines, array $breakdown, array $allowanceStts): void
    {
        $mappedFields = array_merge(
            array_keys(config('payslip_templates.allowance_field_map', [])),
            array_keys(config('payslip_templates.special_field_map', [])),
        );

        $formulaLines = $breakdown['formula_lines'] ?? [];
        if (! is_array($formulaLines)) {
            return;
        }

        $freeSlots = [];
        foreach ($allowanceStts as $stt) {
            if (($lines[$stt]['amount'] ?? 0) == 0.0 && empty($lines[$stt]['label_override'])) {
                $freeSlots[] = $stt;
            }
        }

        foreach ($formulaLines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $target = (string) ($line['target_field'] ?? '');
            $amount = (float) ($line['amount'] ?? 0);
            if ($amount <= 0 || in_array($target, $mappedFields, true)) {
                continue;
            }

            $mappedStt = config("payslip_templates.allowance_field_map.{$target}")
                ?? config("payslip_templates.special_field_map.{$target}");

            if ($mappedStt && isset($lines[$mappedStt]) && ($lines[$mappedStt]['amount'] ?? 0) == 0.0) {
                $lines[$mappedStt] = [
                    'type' => 'money',
                    'amount' => $amount,
                    'label_override' => $line['name'] ?? $line['code'] ?? null,
                ];
                continue;
            }

            if ($freeSlots === []) {
                continue;
            }

            $stt = array_shift($freeSlots);
            $lines[$stt] = [
                'type' => 'money',
                'amount' => $amount,
                'label_override' => $line['name'] ?? $line['code'] ?? null,
            ];
        }
    }

    /** @return list<array<string, mixed>> */
    private function buildDisplayLines(): array
    {
        $definitions = $this->lineDefinitions();

        return array_map(function (array $def) {
            $stt = $def['stt'];
            $value = $this->lineValues[$stt] ?? ['type' => 'money', 'amount' => 0.0];

            return array_merge($def, [
                'type' => $value['type'] ?? 'money',
                'amount' => $value['amount'] ?? 0.0,
                'text' => $value['text'] ?? '',
                'label_vi' => $value['label_override'] ?? $def['label_vi'],
                'display' => $this->formatDisplay($value),
            ]);
        }, $definitions);
    }

    /** @param  array<string, mixed>  $value */
    private function formatDisplay(array $value): string
    {
        return match ($value['type'] ?? 'money') {
            'text' => (string) ($value['text'] ?? ''),
            'days' => $this->formatNumber($value['amount'] ?? 0, 1),
            'hours' => $this->formatNumber($value['amount'] ?? 0, 1),
            default => $this->formatMoney($value['amount'] ?? 0),
        };
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }

    private function formatNumber(float $value, int $decimals = 0): string
    {
        return number_format($value, $decimals, ',', '.');
    }

    private function formatWorkDaysLine(
        float $workDays,
        float $standardDays,
        float $payableTotal,
        float $payableProbation,
        float $payableOfficial,
        bool $hasPhaseSplit,
    ): string {
        $main = $this->formatNumber($workDays, 1)
            .' / '.$this->formatNumber($standardDays, 0)
            .' / '.$this->formatNumber($payableTotal, 1);

        if ($hasPhaseSplit && ($payableProbation > 0 || $payableOfficial > 0)) {
            return $main."\n"
                .'(TV '.$this->formatNumber($payableProbation, 1).' + CT '.$this->formatNumber($payableOfficial, 1).')';
        }

        return $main;
    }

    /** @param  list<string>  $stts */
    private function sumSttRange(array $stts): float
    {
        $total = 0.0;
        foreach ($stts as $stt) {
            $total += (float) ($this->lineValues[$stt]['amount'] ?? 0);
        }

        return $total;
    }

    /** @return list<array<string, string>> */
    private function lineDefinitions(): array
    {
        return [
            ['stt' => '1', 'label_vi' => 'Mã thẻ'],
            ['stt' => '2', 'label_vi' => 'Họ và tên'],
            ['stt' => '3', 'label_vi' => 'Bộ phận'],
            ['stt' => '3b', 'label_vi' => 'Cấp bậc'],
            ['stt' => '4', 'label_vi' => 'Lương cơ bản'],
            ['stt' => '6', 'label_vi' => 'Công: đi làm / chuẩn / tính lương (TV+CT)'],
            ['stt' => '7', 'label_vi' => 'Ngày nghỉ có lương'],
            ['stt' => '8', 'label_vi' => 'Nghỉ không lương'],
            ['stt' => '8a', 'label_vi' => 'Ngày hưởng lương tối thiểu'],
            ['stt' => '9', 'label_vi' => 'Lương (theo công)'],
            ['stt' => '10', 'label_vi' => 'Trợ cấp chức vụ (CV)'],
            ['stt' => '11', 'label_vi' => 'Trợ cấp khác'],
            ['stt' => '12', 'label_vi' => 'Trợ cấp điện thoại'],
            ['stt' => '13', 'label_vi' => 'Hỗ trợ khó khăn sinh hoạt'],
            ['stt' => '14', 'label_vi' => 'Hỗ trợ nhà ở'],
            ['stt' => '15', 'label_vi' => 'Hỗ trợ xăng xe'],
            ['stt' => '16', 'label_vi' => 'Hỗ trợ ăn ca tháng'],
            ['stt' => '17', 'label_vi' => 'Trợ cấp môi trường (tháng 6–10)'],
            ['stt' => '18', 'label_vi' => 'Hỗ trợ nhà ở (>30km)'],
            ['stt' => '19', 'label_vi' => 'Hỗ trợ nuôi con (<72 tháng)'],
            ['stt' => '20', 'label_vi' => 'Hỗ trợ PCCC'],
            ['stt' => '21', 'label_vi' => 'Hỗ trợ an toàn vệ sinh'],
            ['stt' => '22', 'label_vi' => 'Hỗ trợ khám sức khỏe'],
            ['stt' => '22a', 'label_vi' => 'Hỗ trợ nhà ở công tác / BHXH thử việc'],
            ['stt' => '23', 'label_vi' => 'Tổng trợ cấp thực lĩnh'],
            ['stt' => '24', 'label_vi' => 'Hỗ trợ đi lại'],
            ['stt' => '25', 'label_vi' => 'TC ngày thường (150%) — giờ'],
            ['stt' => '26', 'label_vi' => 'TC ngày nghỉ (200%) — giờ'],
            ['stt' => '27', 'label_vi' => 'TC ngày lễ (300%) — giờ'],
            ['stt' => '28', 'label_vi' => 'TC ngày phép năm (300%) — giờ'],
            ['stt' => '29', 'label_vi' => 'Tổng tiền TC ngày'],
            ['stt' => '30', 'label_vi' => 'TC đêm thường (210%) — giờ'],
            ['stt' => '31', 'label_vi' => 'TC đêm nghỉ (270%) — giờ'],
            ['stt' => '32', 'label_vi' => 'TC đêm lễ (390%) — giờ'],
            ['stt' => '33', 'label_vi' => 'TC đêm phép (390%) — giờ'],
            ['stt' => '34', 'label_vi' => 'Tổng tiền TC đêm'],
            ['stt' => '35', 'label_vi' => 'Tổng tiền TC'],
            ['stt' => '35a', 'label_vi' => 'Thưởng duy trì công việc ổn định'],
            ['stt' => '36', 'label_vi' => 'Giờ ca đêm'],
            ['stt' => '37', 'label_vi' => 'Trợ cấp ca đêm'],
            ['stt' => '37a', 'label_vi' => 'Giờ nghỉ kinh nguyệt'],
            ['stt' => '37b', 'label_vi' => 'Trợ cấp nghỉ kinh nguyệt'],
            ['stt' => '38', 'label_vi' => 'Tổng thu nhập'],
            ['stt' => '39', 'label_vi' => 'BHXH/BHYT/BHTN (NLĐ)'],
            ['stt' => '40', 'label_vi' => 'Thuế TNCN'],
            ['stt' => '41', 'label_vi' => 'Trừ khác'],
            ['stt' => '42', 'label_vi' => 'Tổng phải trừ'],
            ['stt' => '43', 'label_vi' => 'Điều chỉnh tháng trước'],
            ['stt' => '44', 'label_vi' => 'Thưởng hiệu suất (theo lương tháng trước)'],
            ['stt' => '44a', 'label_vi' => 'Trợ cấp ăn trưa'],
            ['stt' => '46', 'label_vi' => 'Lương thực lĩnh'],
        ];
    }
}
