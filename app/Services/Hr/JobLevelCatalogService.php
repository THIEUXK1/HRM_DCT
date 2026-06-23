<?php

namespace App\Services\Hr;

use App\Models\JobLevel;

class JobLevelCatalogService
{
    /** @return array<int, array<string, mixed>> */
    public static function gradeDefinitions(): array
    {
        return config('hr_vn.job_grades', []);
    }

    /** @return array<int, string> */
    public static function bands(): array
    {
        return config('hr_vn.job_bands', ['A', 'B', 'C', 'D']);
    }

    /**
     * Đồng bộ thang O1–O7 × band A–D cho một công ty.
     *
     * @return array{created: int, updated: int, deactivated: int}
     */
    public function syncStandardGrades(int $companyId, bool $deactivateLegacy = true): array
    {
        $created = 0;
        $updated = 0;
        $deactivated = 0;

        if ($deactivateLegacy) {
            $deactivated = JobLevel::where('company_id', $companyId)
                ->where(function ($q) {
                    $q->whereNull('grade')
                        ->orWhere('code', 'like', 'LV%');
                })
                ->update(['is_active' => false]);
        }

        $bandIndex = array_flip(self::bands());

        foreach (self::gradeDefinitions() as $gradeDef) {
            $grade = $gradeDef['grade'];
            $baseRank = (int) $gradeDef['rank_base'];

            foreach (self::bands() as $band) {
                $code = "{$grade}-{$band}";
                $offset = ($bandIndex[$band] ?? 0) + 1;
                $salaryStep = (int) ($gradeDef['salary_step'] ?? 1_000_000);
                $minBase = (int) ($gradeDef['salary_min'] ?? 5_000_000);
                $maxBase = (int) ($gradeDef['salary_max'] ?? 15_000_000);
                $bandOffset = $offset - 1;

                $payload = [
                    'name' => $gradeDef['name'].' — Band '.$band,
                    'grade' => $grade,
                    'band' => $band,
                    'category' => $gradeDef['category'],
                    'rank' => $baseRank + $offset,
                    'basic_salary_range_min' => $minBase + ($bandOffset * $salaryStep),
                    'basic_salary_range_max' => $maxBase + ($bandOffset * $salaryStep),
                    'description' => $gradeDef['description'] ?? null,
                    'is_active' => true,
                ];

                $existing = JobLevel::where('company_id', $companyId)->where('code', $code)->first();
                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    JobLevel::create(array_merge($payload, [
                        'company_id' => $companyId,
                        'code' => $code,
                    ]));
                    $created++;
                }
            }
        }

        return compact('created', 'updated', 'deactivated');
    }
}
