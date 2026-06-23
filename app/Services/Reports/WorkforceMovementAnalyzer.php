<?php

namespace App\Services\Reports;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Phân tích biến động nhân sự — tách tuyển mới thực sự vs chuyển trạng thái TV→CT.
 */
class WorkforceMovementAnalyzer
{
    /**
     * @return array{
     *   headcount_start: int,
     *   headcount_end: int,
     *   headcount_start_breakdown: array{total: int, probation: int, official: int},
     *   headcount_end_breakdown: array{total: int, probation: int, official: int},
     *   new_hires: int,
     *   new_hires_note: string,
     *   probation_ended_in_period: int,
     *   converted_to_official_in_period: int,
     *   failed_probation_in_period: int,
     *   conversion_rate: float|null,
     *   internal_probation_to_official: int,
     *   net_headcount_change: int,
     *   narrative: string,
     *   probation_conversions: list<array<string, mixed>>,
     *   failed_probations: list<array<string, mixed>>,
     * }
     */
    public function analyze(
        Builder $employeeScope,
        Carbon $start,
        Carbon $end,
    ): array {
        $startSnapshot = $start->copy()->subDay();
        $startBreakdown = $this->headcountBreakdownAt(clone $employeeScope, $startSnapshot);
        $endBreakdown = $this->headcountBreakdownAt(clone $employeeScope, $end);

        $newHires = (clone $employeeScope)
            ->whereBetween('hire_date', [$start->toDateString(), $end->toDateString()])
            ->count();

        $probationEnded = $this->probationEndedEmployees(clone $employeeScope, $start, $end);
        $converted = $probationEnded->filter(fn (Employee $e) => $this->convertedInPeriod($e, $start, $end));
        $failed = $probationEnded->reject(fn (Employee $e) => $this->convertedInPeriod($e, $start, $end));

        $endedCount = $probationEnded->count();
        $convertedCount = $converted->count();
        $failedCount = $failed->count();
        $conversionRate = $endedCount > 0 ? round(($convertedCount / $endedCount) * 100, 1) : null;

        $netChange = $endBreakdown['total'] - $startBreakdown['total'];

        $narrative = $this->buildNarrative(
            $endedCount,
            $convertedCount,
            $failedCount,
            $conversionRate,
            $newHires,
            $startBreakdown,
            $endBreakdown,
        );

        return [
            'headcount_start' => $startBreakdown['total'],
            'headcount_end' => $endBreakdown['total'],
            'headcount_start_breakdown' => $startBreakdown,
            'headcount_end_breakdown' => $endBreakdown,
            'new_hires' => $newHires,
            'new_hires_note' => 'Chỉ NV bắt đầu làm việc (hire_date) trong kỳ — không gồm chuyển TV→CT.',
            'probation_ended_in_period' => $endedCount,
            'converted_to_official_in_period' => $convertedCount,
            'failed_probation_in_period' => $failedCount,
            'conversion_rate' => $conversionRate,
            'internal_probation_to_official' => $convertedCount,
            'net_headcount_change' => $netChange,
            'narrative' => $narrative,
            'probation_conversions' => $converted->take(30)->map(fn (Employee $e) => $this->mapTransitionRow($e))->values()->all(),
            'failed_probations' => $failed->take(30)->map(fn (Employee $e) => $this->mapTransitionRow($e, true))->values()->all(),
        ];
    }

    /** @return array{total: int, probation: int, official: int} */
    public function headcountBreakdownAt(Builder $scope, Carbon $date): array
    {
        $employees = (clone $scope)
            ->whereDate('hire_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('termination_date')
                    ->orWhereDate('termination_date', '>', $date);
            })
            ->get(['id', 'hire_date', 'probation_end_date', 'official_start_date', 'termination_date']);

        $probation = 0;
        $official = 0;

        foreach ($employees as $employee) {
            if ($this->wasProbationOnDate($employee, $date)) {
                $probation++;
            } else {
                $official++;
            }
        }

        return [
            'total' => $probation + $official,
            'probation' => $probation,
            'official' => $official,
        ];
    }

    /** @return Collection<int, Employee> */
    private function probationEndedEmployees(Builder $scope, Carbon $start, Carbon $end): Collection
    {
        return (clone $scope)
            ->with(['department:id,name', 'position:id,name'])
            ->whereNotNull('probation_end_date')
            ->whereBetween('probation_end_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('probation_end_date')
            ->get();
    }

    private function convertedInPeriod(Employee $employee, Carbon $start, Carbon $end): bool
    {
        if (! $employee->official_start_date) {
            return false;
        }

        $officialStart = Carbon::parse($employee->official_start_date);
        if ($officialStart->lt($start) || $officialStart->gt($end)) {
            return false;
        }

        if ($employee->termination_date) {
            return Carbon::parse($employee->termination_date)->gte($officialStart);
        }

        return (bool) $employee->is_active;
    }

    private function wasProbationOnDate(Employee $employee, Carbon $date): bool
    {
        if (! $employee->hire_date || $date->lt(Carbon::parse($employee->hire_date))) {
            return false;
        }

        if (! $employee->probation_end_date) {
            return false;
        }

        return $date->lte(Carbon::parse($employee->probation_end_date));
    }

    /** @return array<string, mixed> */
    private function mapTransitionRow(Employee $employee, bool $failed = false): array
    {
        return [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'full_name' => $employee->full_name,
            'department' => $employee->department?->name,
            'position' => $employee->position?->name,
            'hire_date' => $employee->hire_date?->format('Y-m-d'),
            'probation_end_date' => $employee->probation_end_date?->format('Y-m-d'),
            'official_start_date' => $employee->official_start_date?->format('Y-m-d'),
            'termination_date' => $employee->termination_date?->format('Y-m-d'),
            'failed' => $failed,
        ];
    }

    private function buildNarrative(
        int $ended,
        int $converted,
        int $failed,
        ?float $conversionRate,
        int $newHires,
        array $startBreakdown,
        array $endBreakdown,
    ): string {
        if ($ended === 0 && $newHires === 0) {
            return 'Trong kỳ không có nhân sự tuyển mới và không có trường hợp kết thúc thử việc.';
        }

        $parts = [];

        if ($ended > 0) {
            $rateText = $conversionRate !== null ? ", đạt tỷ lệ {$conversionRate}%" : '';
            $parts[] = "Trong kỳ có {$ended} nhân sự hết thời gian thử việc, trong đó {$converted} nhân sự được chuyển sang chính thức{$rateText}.";
            $parts[] = 'Các trường hợp chuyển TV→CT không làm tăng tổng headcount vì đã được ghi nhận từ giai đoạn thử việc, nhưng làm thay đổi cơ cấu: thử việc giảm và chính thức tăng tương ứng.';
            if ($failed > 0) {
                $parts[] = "Có {$failed} nhân sự không tiếp tục sau thử việc — cần rà soát chất lượng tuyển dụng và đánh giá thử việc.";
            }
        }

        if ($newHires > 0) {
            $parts[] = "Có {$newHires} nhân sự tuyển mới thực sự trong kỳ (bắt đầu làm việc), làm tăng tổng nhân sự.";
        }

        $probationDelta = $endBreakdown['probation'] - $startBreakdown['probation'];
        $officialDelta = $endBreakdown['official'] - $startBreakdown['official'];
        if ($probationDelta !== 0 || $officialDelta !== 0) {
            $parts[] = "Cơ cấu: thử việc {$startBreakdown['probation']} → {$endBreakdown['probation']} (".($probationDelta >= 0 ? '+' : '')."{$probationDelta}), chính thức {$startBreakdown['official']} → {$endBreakdown['official']} (".($officialDelta >= 0 ? '+' : '')."{$officialDelta}).";
        }

        return implode(' ', $parts);
    }
}
