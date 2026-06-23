<?php

namespace App\Services\Attendance;

use App\Models\WorkScheduleGroup;
use App\Models\WorkSchedulePattern;
use Illuminate\Support\Facades\DB;

class WorkScheduleSetupService
{
    /**
     * Tạo nhóm + mẫu ca mặc định cho công ty mới.
     *
     * @return array{groups: int, patterns: int}
     */
    public function seedDefaults(int $companyId): array
    {
        $groupCount = 0;
        $patternCount = 0;

        DB::transaction(function () use ($companyId, &$groupCount, &$patternCount) {
            foreach (config('work_schedule_vn.default_groups', []) as $groupDef) {
                $group = WorkScheduleGroup::updateOrCreate(
                    ['company_id' => $companyId, 'code' => $groupDef['code']],
                    [
                        'name' => $groupDef['name'],
                        'group_type' => $groupDef['group_type'],
                        'description' => $groupDef['description'] ?? null,
                        'is_active' => true,
                    ],
                );
                $group->wasRecentlyCreated ? $groupCount++ : null;

                $presets = $groupDef['group_type'] === 'production'
                    ? ['6D8H']
                    : ['5D8H'];

                foreach ($presets as $presetCode) {
                    $preset = config("work_schedule_vn.pattern_presets.{$presetCode}");
                    if (! $preset) {
                        continue;
                    }

                    $pattern = WorkSchedulePattern::updateOrCreate(
                        [
                            'company_id' => $companyId,
                            'code' => $groupDef['code'].'-'.$presetCode,
                        ],
                        [
                            'work_schedule_group_id' => $group->id,
                            'name' => $preset['name'],
                            'pattern_code' => $presetCode,
                            'hours_per_day' => $preset['hours_per_day'],
                            'work_days' => $preset['work_days'],
                            'rest_days' => $preset['rest_days'],
                            'allow_weekend_swap' => $preset['allow_weekend_swap'],
                            'allow_continuous' => $preset['allow_continuous'],
                            'max_consecutive_work_days' => (int) config('work_schedule_vn.max_consecutive_work_days', 13),
                            'swap_rest_day' => $preset['allow_weekend_swap'] ? 6 : null,
                            'swap_work_day' => $preset['allow_weekend_swap'] ? 7 : null,
                            'is_active' => true,
                        ],
                    );
                    $pattern->wasRecentlyCreated ? $patternCount++ : null;
                }
            }
        });

        return ['groups' => $groupCount, 'patterns' => $patternCount];
    }
}
