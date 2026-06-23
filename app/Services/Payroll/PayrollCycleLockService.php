<?php

namespace App\Services\Payroll;

use App\Models\PayrollCycle;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollCycleLockService
{
    public function lock(PayrollCycle $cycle, ?User $user = null): PayrollCycle
    {
        if ($cycle->status === 'locked') {
            throw new RuntimeException('Kỳ lương đã khóa.');
        }

        if ($cycle->status !== 'calculated' || $cycle->results()->count() === 0) {
            throw new RuntimeException('Phải tính lương xong trước khi khóa kỳ.');
        }

        $cycle->update([
            'status' => 'locked',
            'locked_at' => now(),
            'locked_by' => $user?->id,
        ]);

        // Tự động tạo bút toán hạch toán lương & KPCĐ
        try {
            app(PayrollJournalService::class)->generateForCycle($cycle);
        } catch (\Exception $e) {
            // Log warning or rethrow depending on audit logs strategy
            logger()->warning('Failed to generate payroll journal entries: ' . $e->getMessage());
        }

        AuditLogger::finalized($cycle, "Khóa lương tháng {$cycle->period}");

        $this->notifyEmployeesPayrollLocked($cycle);

        return $cycle->fresh(['results']);
    }

    public function unlock(PayrollCycle $cycle, User $user, ?string $reason = null): PayrollCycle
    {
        if (! $user->hasRole('admin')) {
            throw new RuntimeException('Chỉ admin mới được mở khóa lương tháng.');
        }

        if ($cycle->status !== 'locked') {
            throw new RuntimeException('Kỳ lương chưa ở trạng thái khóa.');
        }

        $cycle->update([
            'status' => 'calculated',
            'unlocked_at' => now(),
            'unlocked_by' => $user->id,
        ]);

        // Xóa bút toán nháp khi mở khóa kỳ lương
        \App\Models\PayrollJournalEntry::where('payroll_cycle_id', $cycle->id)
            ->where('status', 'draft')
            ->delete();

        AuditLogger::log(
            'payroll_cycle_unlocked',
            $cycle,
            null,
            'payroll',
            "Admin mở khóa lương tháng {$cycle->period}".($reason ? ": {$reason}" : ''),
        );

        return $cycle->fresh(['results']);
    }

    public function assertNotLocked(int $companyId, string $period): void
    {
        $latest = PayrollCycle::where('company_id', $companyId)
            ->where('period', $period)
            ->orderByDesc('run_number')
            ->first();

        if ($latest && $latest->status === 'locked') {
            throw new RuntimeException(
                "Bảng lương {$period} (lần {$latest->run_number}) đã khóa. Tạo «bảng lương tính lại» mới để chỉnh trợ cấp hoặc tính lại.",
            );
        }
    }

    private function notifyEmployeesPayrollLocked(PayrollCycle $cycle): void
    {
        foreach ($cycle->results()->with('employee:id,user_id')->get() as $result) {
            if ($result->employee?->user_id) {
                NotificationService::payrollFinalized($cycle, $result->employee->user_id);
            }
        }
    }
}
