<?php

namespace App\Services\Attendance;

use App\Models\AttendancePeriodLock;
use App\Models\AttendanceSummary;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AttendancePeriodLockService
{
    public function __construct(
        private readonly AttendanceSummaryService $summaryService,
        private readonly WorkScheduleComplianceService $complianceService,
    ) {}

    public function isLocked(int $companyId, string $period): bool
    {
        return AttendancePeriodLock::where('company_id', $companyId)
            ->where('period', $period)
            ->whereNull('unlocked_at')
            ->exists();
    }

    /** @return array<string, mixed>|null */
    public function status(int $companyId, string $period): ?array
    {
        $lock = AttendancePeriodLock::with(['lockedByUser:id,name,email', 'unlockedByUser:id,name,email'])
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->first();

        if (! $lock) {
            return [
                'period' => $period,
                'is_locked' => false,
                'summary_locked_count' => AttendanceSummary::where('company_id', $companyId)
                    ->where('period', $period)->where('is_locked', true)->count(),
                'summary_total' => AttendanceSummary::where('company_id', $companyId)
                    ->where('period', $period)->count(),
            ];
        }

        return [
            'period' => $period,
            'is_locked' => $lock->isActive(),
            'locked_at' => optional($lock->locked_at)->toIso8601String(),
            'locked_by' => $lock->lockedByUser?->name,
            'unlocked_at' => optional($lock->unlocked_at)->toIso8601String(),
            'unlocked_by' => $lock->unlockedByUser?->name,
            'notes' => $lock->notes,
            'summary_locked_count' => AttendanceSummary::where('company_id', $companyId)
                ->where('period', $period)->where('is_locked', true)->count(),
            'summary_total' => AttendanceSummary::where('company_id', $companyId)
                ->where('period', $period)->count(),
        ];
    }

    public function assertNotLocked(int $companyId, string $period): void
    {
        if ($this->isLocked($companyId, $period)) {
            throw new RuntimeException("Kỳ công {$period} đã khóa. Chỉ admin mới được mở khóa.");
        }
    }

    public function lock(int $companyId, string $period, ?User $user = null, ?string $notes = null): array
    {
        if ($this->isLocked($companyId, $period)) {
            throw new RuntimeException("Kỳ công {$period} đã được khóa.");
        }

        return DB::transaction(function () use ($companyId, $period, $user, $notes) {
            $this->summaryService->buildForPeriod($companyId, $period);
            $lockedRecords = $this->summaryService->lockPeriod($companyId, $period);

            $lock = AttendancePeriodLock::create([
                'company_id' => $companyId,
                'period' => $period,
                'locked_by' => $user?->id,
                'locked_at' => now(),
                'notes' => $notes,
            ]);

            $alerts = $this->complianceService->listCompanyAlerts($companyId, $period);
            if ($alerts !== []) {
                NotificationService::complianceAlerts($companyId, $period, $alerts);
            }

            AuditLogger::finalized($lock, "Khóa công tháng {$period} ({$lockedRecords} NV)");

            return [
                'period' => $period,
                'locked_records' => $lockedRecords,
                'compliance_alerts' => count($alerts),
                'lock' => $lock->load('lockedByUser:id,name'),
            ];
        });
    }

    public function unlock(int $companyId, string $period, User $user, ?string $reason = null): array
    {
        if (! $user->hasRole('admin')) {
            throw new RuntimeException('Chỉ admin mới được mở khóa công tháng.');
        }

        $lock = AttendancePeriodLock::where('company_id', $companyId)
            ->where('period', $period)
            ->whereNull('unlocked_at')
            ->first();

        if (! $lock) {
            throw new RuntimeException("Kỳ công {$period} chưa khóa hoặc đã mở khóa.");
        }

        return DB::transaction(function () use ($companyId, $period, $user, $reason, $lock) {
            AttendanceSummary::where('company_id', $companyId)
                ->where('period', $period)
                ->update([
                    'is_locked' => false,
                    'locked_at' => null,
                ]);

            $lock->update([
                'unlocked_by' => $user->id,
                'unlocked_at' => now(),
                'notes' => trim(($lock->notes ?? '').($reason ? "\nMở khóa: {$reason}" : '')),
            ]);

            AuditLogger::log(
                'attendance_period_unlocked',
                $lock,
                null,
                'attendance',
                "Admin mở khóa công tháng {$period}",
            );

            return [
                'period' => $period,
                'unlocked_at' => $lock->unlocked_at?->toIso8601String(),
                'unlocked_by' => $user->name,
            ];
        });
    }
}
