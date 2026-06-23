<?php

namespace App\Services\Attendance;

use App\Models\EmployeeWorkSchedule;
use App\Models\WorkSchedulePattern;
use App\Models\WorkScheduleWeekOverride;
use Carbon\Carbon;

/**
 * Xác định lịch làm việc hiện hành của NV theo nhóm / mẫu ca.
 */
class WorkScheduleResolver
{
    public function activeSchedule(int $employeeId, ?Carbon $onDate = null): ?EmployeeWorkSchedule
    {
        $date = ($onDate ?? now())->toDateString();

        return EmployeeWorkSchedule::with(['group', 'pattern', 'pattern.workShift'])
            ->where('employee_id', $employeeId)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    public function isScheduledWorkDay(int $employeeId, Carbon $date): bool
    {
        $schedule = $this->activeSchedule($employeeId, $date);
        if (! $schedule?->pattern) {
            return ! $date->isWeekend();
        }

        return $this->isWorkDayForPattern($schedule->pattern, $schedule, $date);
    }

    public function isWorkDayForPattern(
        WorkSchedulePattern $pattern,
        EmployeeWorkSchedule $schedule,
        Carbon $date,
    ): bool {
        $iso = (int) $date->isoWeekday();
        $workDays = array_map('intval', $pattern->work_days ?? []);

        $swap = $this->resolveWeekendSwap($schedule, $pattern, $date);

        if ($swap['enabled']) {
            if ($iso === $swap['rest']) {
                return false;
            }
            if ($iso === $swap['work']) {
                return true;
            }
        }

        return in_array($iso, $workDays, true);
    }

    /** @return array{enabled: bool, rest: int, work: int} */
    private function resolveWeekendSwap(
        EmployeeWorkSchedule $schedule,
        WorkSchedulePattern $pattern,
        Carbon $date,
    ): array {
        $disabled = ['enabled' => false, 'rest' => 6, 'work' => 7];

        if (! $schedule->weekend_swap_enabled || ! $pattern->allow_weekend_swap) {
            return $disabled;
        }

        $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $override = WorkScheduleWeekOverride::where('employee_id', $schedule->employee_id)
            ->where('week_start', $weekStart)
            ->first();

        if ($override) {
            if (! $override->swap_enabled) {
                return $disabled;
            }

            return [
                'enabled' => true,
                'rest' => (int) $override->swap_rest_day,
                'work' => (int) $override->swap_work_day,
            ];
        }

        return [
            'enabled' => true,
            'rest' => (int) ($pattern->swap_rest_day ?? 6),
            'work' => (int) ($pattern->swap_work_day ?? 7),
        ];
    }

    /** @return array<string, mixed>|null */
    public function summaryForEmployee(int $employeeId, ?Carbon $onDate = null): ?array
    {
        $schedule = $this->activeSchedule($employeeId, $onDate);
        if (! $schedule) {
            return null;
        }

        $pattern = $schedule->pattern;
        $group = $schedule->group;

        return [
            'schedule_id' => $schedule->id,
            'group' => [
                'id' => $group?->id,
                'code' => $group?->code,
                'name' => $group?->name,
                'group_type' => $group?->group_type,
                'group_type_label' => config("work_schedule_vn.group_types.{$group?->group_type}", $group?->group_type),
            ],
            'pattern' => [
                'id' => $pattern?->id,
                'code' => $pattern?->code,
                'name' => $pattern?->name,
                'pattern_code' => $pattern?->pattern_code,
                'hours_per_day' => (float) ($pattern?->hours_per_day ?? 8),
                'work_days' => $pattern?->work_days ?? [],
                'rest_days' => $pattern?->rest_days ?? [],
                'allow_weekend_swap' => (bool) $pattern?->allow_weekend_swap,
                'allow_continuous' => (bool) $pattern?->allow_continuous,
                'max_consecutive_work_days' => (int) ($pattern?->max_consecutive_work_days
                    ?? config('work_schedule_vn.max_consecutive_work_days', 13)),
            ],
            'weekend_swap_enabled' => $schedule->weekend_swap_enabled,
            'effective_from' => $schedule->effective_from?->format('Y-m-d'),
            'effective_to' => $schedule->effective_to?->format('Y-m-d'),
        ];
    }
}
