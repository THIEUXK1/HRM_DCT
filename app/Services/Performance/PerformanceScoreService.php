<?php

namespace App\Services\Performance;

use App\Models\EmployeeReview;
use App\Models\Goal;

class PerformanceScoreService
{
    public function goalProgress(Goal $goal): ?float
    {
        if ($goal->target_value === null || (float) $goal->target_value <= 0) {
            return null;
        }

        $actual = (float) ($goal->actual_value ?? 0);
        $target = (float) $goal->target_value;

        return min(100, round(($actual / $target) * 100, 2));
    }

    public function employeeKpiScore(int $employeeId, int $cycleId): ?float
    {
        $goals = Goal::query()
            ->where('employee_id', $employeeId)
            ->where('performance_cycle_id', $cycleId)
            ->where('status', '!=', 'cancelled')
            ->get();

        if ($goals->isEmpty()) {
            return null;
        }

        $totalWeight = $goals->sum(fn (Goal $g) => (float) ($g->weight ?: 0));
        if ($totalWeight <= 0) {
            $totalWeight = $goals->count();
            $goals->each(fn (Goal $g) => $g->weight = 1);
        }

        $weighted = 0.0;
        foreach ($goals as $goal) {
            $progress = $this->goalProgress($goal);
            if ($progress === null) {
                continue;
            }
            $weight = (float) ($goal->weight ?: 1);
            $weighted += $progress * ($weight / $totalWeight);
        }

        return round($weighted, 2);
    }

    public function behaviorScore(?float $self, ?float $manager): ?float
    {
        if ($self !== null && $manager !== null) {
            return round(($self + $manager) / 2, 2);
        }

        return $self ?? $manager;
    }

    public function ratingFromScore(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'E',
        };
    }

    public function finalize(EmployeeReview $review): EmployeeReview
    {
        $kpiWeight = config('performance.weights.kpi', 60) / 100;
        $behaviorWeight = config('performance.weights.behavior', 40) / 100;

        $kpiScore = $this->employeeKpiScore($review->employee_id, $review->performance_cycle_id);
        $behaviorScore = $this->behaviorScore(
            $review->self_score !== null ? (float) $review->self_score : null,
            $review->manager_score !== null ? (float) $review->manager_score : null,
        );

        $final = null;
        if ($kpiScore !== null && $behaviorScore !== null) {
            $final = round(($kpiScore * $kpiWeight) + ($behaviorScore * $behaviorWeight), 2);
        } elseif ($kpiScore !== null) {
            $final = $kpiScore;
        } elseif ($behaviorScore !== null) {
            $final = $behaviorScore;
        }

        $review->update([
            'final_score' => $final,
            'rating' => $this->ratingFromScore($final),
            'status' => $final !== null ? 'completed' : $review->status,
        ]);

        return $review->fresh(['employee', 'cycle']);
    }
}
