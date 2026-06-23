<?php

namespace App\Services\Hr;

use App\Models\ApprovalInstance;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\OvertimeRequest;
use App\Services\Attendance\OvertimeCapValidator;
use App\Services\Attendance\WorkScheduleComplianceService;
use Carbon\Carbon;

/**
 * Cảnh báo tuân thủ HR tập trung — HĐ, OT, thử việc, ca làm việc.
 * Tham chiếu BLLĐ 2019 (Điều 20, 24–27, 107) và NĐ 145/2020/NĐ-CP.
 */
class HrComplianceAlertService
{
    public function __construct(
        private readonly WorkScheduleComplianceService $workScheduleCompliance,
    ) {}

    /** @return array{period: string, counts: array<string, int>, total: int} */
    public function summary(int $companyId, ?string $period = null): array
    {
        $period = $period ?? now()->format('Y-m');
        $items = $this->collect($companyId, $period);
        $counts = [];
        foreach ($items as $item) {
            $key = $item['category'];
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return [
            'period' => $period,
            'counts' => $counts,
            'total' => count($items),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(int $companyId, ?string $period = null, ?string $category = null, int $limit = 100): array
    {
        $period = $period ?? now()->format('Y-m');
        $items = $this->collect($companyId, $period);

        if ($category) {
            $items = array_values(array_filter($items, fn ($a) => $a['category'] === $category));
        }

        usort($items, fn ($a, $b) => ($b['severity_rank'] ?? 0) <=> ($a['severity_rank'] ?? 0));

        return array_slice($items, 0, $limit);
    }

    /** Dashboard quản lý — gom số lượng theo nhóm nghiệp vụ. */
    public function dashboardCounts(int $companyId, ?string $period = null): array
    {
        $summary = $this->summary($companyId, $period);
        $c = $summary['counts'];

        return [
            'contractsExpiring' => ($c['contract_expiring'] ?? 0) + ($c['contract_expiring_soon'] ?? 0),
            'contractsExpired' => $c['contract_expired'] ?? 0,
            'contractsMissing' => $c['contract_missing'] ?? 0,
            'contractsNoFile' => $c['contract_no_file'] ?? 0,
            'probationEnding' => $c['probation_ending'] ?? 0,
            'otMonthlyWarning' => ($c['ot_monthly_warning'] ?? 0) + ($c['ot_monthly_exceeded'] ?? 0),
            'otYearlyWarning' => ($c['ot_yearly_warning'] ?? 0) + ($c['ot_yearly_exceeded'] ?? 0) + ($c['ot_yearly_notify_authority'] ?? 0),
            'workSchedule' => $c['work_schedule'] ?? 0,
            'pendingApprovals' => ApprovalInstance::where('status', 'pending')->count(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function collect(int $companyId, string $period): array
    {
        return array_merge(
            $this->contractAlerts($companyId),
            $this->probationAlerts($companyId),
            $this->overtimeAlerts($companyId, $period),
            $this->workScheduleAlerts($companyId, $period),
        );
    }

    /** @return list<array<string, mixed>> */
    private function contractAlerts(int $companyId): array
    {
        $today = now()->startOfDay();
        $days30 = (int) (config('hr_vn.alert_thresholds.contract_expiring_days')[0] ?? 30);
        $days60 = (int) (config('hr_vn.alert_thresholds.contract_expiring_days')[1] ?? 60);
        $maxDefinite = (int) config('hr_vn.contract_max_definite_months', 36);
        $maxSeasonal = (int) config('hr_vn.contract_max_seasonal_months', 12);

        $contracts = EmploymentContract::query()
            ->with('employee:id,full_name,employee_code,department_id,employment_status')
            ->whereHas('employee', fn ($q) => $q->where('company_id', $companyId))
            ->where('status', 'active')
            ->get();

        $alerts = [];

        foreach ($contracts as $contract) {
            $emp = $contract->employee;
            if (! $emp) {
                continue;
            }

            $base = [
                'employee_id' => $emp->id,
                'employee_code' => $emp->employee_code,
                'full_name' => $emp->full_name,
                'entity_type' => 'employment_contract',
                'entity_id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'action_url' => '/contracts',
            ];

            if ($contract->end_date) {
                $end = Carbon::parse($contract->end_date)->startOfDay();
                $daysLeft = $today->diffInDays($end, false);

                if ($daysLeft < 0) {
                    $alerts[] = $this->makeAlert(
                        category: 'contract_expired',
                        severity: 'urgent',
                        title: 'Hợp đồng đã hết hạn',
                        message: "HĐ {$contract->contract_number} của {$emp->full_name} hết hạn ngày {$contract->end_date->format('d/m/Y')}. Cần gia hạn hoặc chấm dứt HĐLĐ.",
                        legal: 'Điều 20, 36 BLLĐ 2019',
                        extra: array_merge($base, ['days_left' => $daysLeft]),
                    );
                } elseif ($daysLeft <= $days30) {
                    $alerts[] = $this->makeAlert(
                        category: 'contract_expiring',
                        severity: $daysLeft <= 7 ? 'urgent' : 'warning',
                        title: 'Hợp đồng sắp hết hạn',
                        message: "HĐ {$contract->contract_number} — {$emp->full_name} còn {$daysLeft} ngày (hết {$contract->end_date->format('d/m/Y')}).",
                        legal: 'Điều 20 BLLĐ 2019 — chuẩn bị gia hạn / ký HĐ mới',
                        extra: array_merge($base, ['days_left' => $daysLeft]),
                    );
                } elseif ($daysLeft <= $days60) {
                    $alerts[] = $this->makeAlert(
                        category: 'contract_expiring_soon',
                        severity: 'info',
                        title: 'Theo dõi thời hạn HĐ',
                        message: "HĐ {$contract->contract_number} — {$emp->full_name} còn {$daysLeft} ngày.",
                        legal: 'Điều 20 BLLĐ 2019',
                        extra: array_merge($base, ['days_left' => $daysLeft]),
                    );
                }
            }

            if (! $contract->file_path) {
                $alerts[] = $this->makeAlert(
                    category: 'contract_no_file',
                    severity: 'warning',
                    title: 'Thiếu file scan HĐ',
                    message: "HĐ {$contract->contract_number} của {$emp->full_name} chưa có bản scan/PDF.",
                    legal: 'Nghị định 145/2020 — lưu trữ hồ sơ lao động',
                    extra: $base,
                );
            }

            if ($contract->contract_type === 'definite' && $contract->start_date && $contract->end_date) {
                $months = Carbon::parse($contract->start_date)->diffInMonths(Carbon::parse($contract->end_date));
                if ($months > $maxDefinite) {
                    $alerts[] = $this->makeAlert(
                        category: 'contract_duration',
                        severity: 'warning',
                        title: 'Thời hạn HĐ xác định vượt quy định',
                        message: "HĐ {$contract->contract_number} kéo dài {$months} tháng (tối đa {$maxDefinite} tháng/lần ký).",
                        legal: 'Điều 20 khoản 2 BLLĐ 2019',
                        extra: array_merge($base, ['duration_months' => $months]),
                    );
                }
            }

            if ($contract->contract_type === 'seasonal' && $contract->start_date && $contract->end_date) {
                $months = Carbon::parse($contract->start_date)->diffInMonths(Carbon::parse($contract->end_date));
                if ($months > $maxSeasonal) {
                    $alerts[] = $this->makeAlert(
                        category: 'contract_duration',
                        severity: 'warning',
                        title: 'HĐ mùa vụ vượt 12 tháng',
                        message: "HĐ {$contract->contract_number} — {$months} tháng (tối đa {$maxSeasonal} tháng).",
                        legal: 'Điều 20 khoản 2 BLLĐ 2019',
                        extra: array_merge($base, ['duration_months' => $months]),
                    );
                }
            }
        }

        $activeEmployeeIds = Employee::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('employment_status', ['active', 'probation'])
            ->pluck('id');

        $withContract = EmploymentContract::query()
            ->whereIn('employee_id', $activeEmployeeIds)
            ->where('status', 'active')
            ->pluck('employee_id')
            ->unique();

        $missing = Employee::query()
            ->whereIn('id', $activeEmployeeIds->diff($withContract))
            ->get(['id', 'full_name', 'employee_code']);

        foreach ($missing as $emp) {
            $alerts[] = $this->makeAlert(
                category: 'contract_missing',
                severity: 'urgent',
                title: 'Nhân viên chưa có HĐ hiệu lực',
                message: "{$emp->full_name} ({$emp->employee_code}) đang làm việc nhưng chưa có hợp đồng active.",
                legal: 'Điều 14 BLLĐ 2019 — bắt buộc ký HĐLĐ bằng văn bản',
                extra: [
                    'employee_id' => $emp->id,
                    'employee_code' => $emp->employee_code,
                    'full_name' => $emp->full_name,
                    'action_url' => '/contracts',
                ],
            );
        }

        return $alerts;
    }

    /** @return list<array<string, mixed>> */
    private function probationAlerts(int $companyId): array
    {
        $daysAhead = (int) (config('hr_vn.alert_thresholds.probation_ending_days', 14));
        $today = now()->toDateString();
        $threshold = now()->addDays($daysAhead)->toDateString();

        $employees = Employee::query()
            ->where('company_id', $companyId)
            ->where('employment_status', 'probation')
            ->whereNotNull('official_start_date')
            ->whereBetween('official_start_date', [$today, $threshold])
            ->get(['id', 'full_name', 'employee_code', 'official_start_date']);

        $alerts = [];
        foreach ($employees as $emp) {
            $daysLeft = now()->startOfDay()->diffInDays(Carbon::parse($emp->official_start_date), false);
            $alerts[] = $this->makeAlert(
                category: 'probation_ending',
                severity: $daysLeft <= 7 ? 'urgent' : 'warning',
                title: 'Sắp hết thử việc',
                message: "{$emp->full_name} kết thúc thử việc ngày {$emp->official_start_date->format('d/m/Y')} (còn {$daysLeft} ngày).",
                legal: 'Điều 24–27 BLLĐ 2019 — quyết định chính thức hoặc chấm dứt',
                extra: [
                    'employee_id' => $emp->id,
                    'employee_code' => $emp->employee_code,
                    'full_name' => $emp->full_name,
                    'days_left' => $daysLeft,
                    'action_url' => '/employees/'.$emp->id,
                ],
            );
        }

        return $alerts;
    }

    /** @return list<array<string, mixed>> */
    private function overtimeAlerts(int $companyId, string $period): array
    {
        $monthlyMax = (float) config('hr_vn.ot_monthly_max_hours', OvertimeCapValidator::MAX_MONTHLY_HOURS);
        $yearlyMax = (float) config('hr_vn.ot_yearly_max_hours', OvertimeCapValidator::MAX_YEARLY_HOURS);
        $monthlyWarn = $monthlyMax * (float) config('hr_vn.alert_thresholds.ot_monthly_warning_pct', 0.8);
        $yearlyWarn = $yearlyMax * (float) config('hr_vn.alert_thresholds.ot_yearly_warning_pct', 0.8);
        $notifyMin = (float) config('hr_vn.alert_thresholds.ot_yearly_notify_min_hours', 200);
        $year = (int) Carbon::createFromFormat('Y-m', $period)->year;

        $employees = Employee::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get(['id', 'full_name', 'employee_code']);

        $alerts = [];

        foreach ($employees as $emp) {
            $monthly = OvertimeCapValidator::summary($emp->id, $period);
            $monthlyUsed = (float) $monthly['monthly_used'];

            if ($monthlyUsed >= $monthlyWarn) {
                $exceeded = $monthlyUsed > $monthlyMax;
                $alerts[] = $this->makeAlert(
                    category: $exceeded ? 'ot_monthly_exceeded' : 'ot_monthly_warning',
                    severity: $exceeded ? 'urgent' : 'warning',
                    title: $exceeded ? 'Vượt giới hạn OT tháng' : 'OT tháng gần chạm trần',
                    message: sprintf(
                        '%s — %.1fh/%s tháng %s (tối đa %.0fh/tháng).',
                        $emp->full_name,
                        $monthlyUsed,
                        $period,
                        $exceeded ? 'đã vượt' : 'sắp chạm',
                        $monthlyMax,
                    ),
                    legal: 'Điều 107 BLLĐ 2019 · NĐ 145/2020 Điều 60',
                    extra: [
                        'employee_id' => $emp->id,
                        'employee_code' => $emp->employee_code,
                        'full_name' => $emp->full_name,
                        'hours_used' => $monthlyUsed,
                        'hours_max' => $monthlyMax,
                        'period' => $period,
                        'action_url' => '/attendance',
                    ],
                );
            }

            $yearlyUsed = (float) $monthly['yearly_used'];
            if ($yearlyUsed < $yearlyWarn) {
                continue;
            }

            $exceeded = $yearlyUsed > $yearlyMax;
            $needNotify = $yearlyUsed >= $notifyMin && $yearlyUsed <= OvertimeCapValidator::MAX_YEARLY_HOURS_SPECIAL;

            if ($exceeded) {
                $category = 'ot_yearly_exceeded';
                $severity = 'urgent';
                $title = 'Vượt giới hạn OT năm';
            } elseif ($needNotify) {
                $category = 'ot_yearly_notify_authority';
                $severity = 'warning';
                $title = 'Cần thông báo Sở LĐ-TB&XH';
            } else {
                $category = 'ot_yearly_warning';
                $severity = 'warning';
                $title = 'OT năm gần chạm trần';
            }

            $message = sprintf(
                '%s — %.1fh/%d (tối đa %.0fh/năm).',
                $emp->full_name,
                $yearlyUsed,
                $year,
                $yearlyMax,
            );
            if ($category === 'ot_yearly_notify_authority') {
                $message .= ' Cần thông báo Sở LĐ-TB&XH nếu tổ chức OT từ 200–300h/năm (Mẫu 02/PLIV NĐ 145/2020).';
            }

            $alerts[] = $this->makeAlert(
                category: $category,
                severity: $severity,
                title: $title,
                message: $message,
                legal: 'Điều 107 BLLĐ 2019 · NĐ 145/2020 Điều 62',
                extra: [
                    'employee_id' => $emp->id,
                    'employee_code' => $emp->employee_code,
                    'full_name' => $emp->full_name,
                    'hours_used' => $yearlyUsed,
                    'hours_max' => $yearlyMax,
                    'year' => $year,
                    'action_url' => '/attendance',
                ],
            );
        }

        return $alerts;
    }

    /** @return list<array<string, mixed>> */
    private function workScheduleAlerts(int $companyId, string $period): array
    {
        $raw = $this->workScheduleCompliance->listCompanyAlerts($companyId, $period);
        $alerts = [];

        foreach ($raw as $item) {
            $severity = match ($item['severity'] ?? 'info') {
                'warning' => 'warning',
                default => 'info',
            };

            $alerts[] = $this->makeAlert(
                category: 'work_schedule',
                severity: $severity,
                title: match ($item['type'] ?? '') {
                    'consecutive_days' => 'Làm liên tục quá số ngày cho phép',
                    'ot_monthly', 'ot_yearly' => 'OT vượt mức (ca làm việc)',
                    'ot_excess_payroll' => 'OT vượt mức — tách khỏi lương',
                    default => 'Cảnh báo tuân thủ ca làm',
                },
                message: $item['message'] ?? '',
                legal: 'BLLĐ 2019 · quy chế ca làm việc công ty',
                extra: [
                    'employee_id' => $item['employee_id'] ?? null,
                    'employee_code' => $item['employee_code'] ?? null,
                    'full_name' => $item['full_name'] ?? null,
                    'alert_type' => $item['type'] ?? null,
                    'action_url' => '/work-schedules',
                ],
            );
        }

        return $alerts;
    }

    /** @param array<string, mixed> $extra */
    private function makeAlert(
        string $category,
        string $severity,
        string $title,
        string $message,
        string $legal,
        array $extra = [],
    ): array {
        $rank = match ($severity) {
            'urgent' => 3,
            'warning' => 2,
            default => 1,
        };

        return array_merge([
            'category' => $category,
            'severity' => $severity,
            'severity_rank' => $rank,
            'title' => $title,
            'message' => $message,
            'legal_reference' => $legal,
        ], $extra);
    }
}
