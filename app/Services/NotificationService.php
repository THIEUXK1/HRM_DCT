<?php

namespace App\Services;

use App\Models\HrNotification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    // ── Generic factory ───────────────────────────────────────────────────

    public static function send(
        int    $userId,
        string $type,
        string $title,
        string $body       = '',
        string $priority   = HrNotification::PRIORITY_NORMAL,
        ?string $actionUrl = null,
        mixed  $entity     = null,
        ?int   $companyId  = null,
        ?int   $tenantId   = null,
    ): HrNotification {
        $entityType = $entity ? get_class($entity) : null;
        $entityId   = $entity?->id ?? null;

        $notif = HrNotification::create([
            'user_id'     => $userId,
            'company_id'  => $companyId,
            'tenant_id'   => $tenantId,
            'type'        => $type,
            'title'       => $title,
            'body'        => $body,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'action_url'  => $actionUrl,
            'priority'    => $priority,
        ]);

        // Invalidate badge count cache
        Cache::forget("notif_unread_count:{$userId}");

        return $notif;
    }

    // ── Domain helpers ────────────────────────────────────────────────────

    /** Cảnh báo tuân thủ ca làm / OT — gửi HR khi khóa công. */
    public static function complianceAlerts(int $companyId, string $period, array $alerts): void
    {
        if ($alerts === []) {
            return;
        }

        $warningCount = count(array_filter($alerts, fn ($a) => ($a['severity'] ?? '') === 'warning'));
        $body = collect($alerts)->take(3)->pluck('message')->implode("\n");

        static::notifyHrManagers(
            companyId: $companyId,
            type: HrNotification::TYPE_COMPLIANCE_ALERT,
            title: "⚠️ Cảnh báo tuân thủ công tháng {$period} ({$warningCount} cảnh báo)",
            body: $body,
            priority: $warningCount > 0 ? HrNotification::PRIORITY_HIGH : HrNotification::PRIORITY_NORMAL,
            actionUrl: '/work-schedules',
        );
    }

    /** Notify when a leave request is approved/rejected. */
    public static function leaveDecision(\App\Models\LeaveRequest $leave, string $decision): void
    {
        if (! $leave->employee?->user_id) return;

        $type  = $decision === 'approved'
            ? HrNotification::TYPE_LEAVE_APPROVED
            : HrNotification::TYPE_LEAVE_REJECTED;

        $emoji = $decision === 'approved' ? '✅' : '❌';
        $label = $decision === 'approved' ? 'được duyệt' : 'bị từ chối';

        self::send(
            userId:    $leave->employee->user_id,
            type:      $type,
            title:     "{$emoji} Nghỉ phép {$label}",
            body:      "Yêu cầu nghỉ phép {$leave->start_date} → {$leave->end_date} đã {$label}.",
            priority:  HrNotification::PRIORITY_NORMAL,
            actionUrl: '/leave-requests',
            entity:    $leave,
            companyId: $leave->company_id ?? null,
        );
    }

    /** Notify when an OT request is approved/rejected. */
    public static function otDecision(\App\Models\OvertimeRequest $ot, string $decision): void
    {
        if (! $ot->employee?->user_id) return;

        $label = $decision === 'approved' ? 'được duyệt' : 'bị từ chối';
        $emoji = $decision === 'approved' ? '✅' : '❌';

        self::send(
            userId:    $ot->employee->user_id,
            type:      HrNotification::TYPE_OT_APPROVED,
            title:     "{$emoji} Tăng ca {$label}",
            body:      "Yêu cầu tăng ca ngày {$ot->work_date} ({$ot->hours}h) đã {$label}.",
            actionUrl: '/attendance',
            entity:    $ot,
            companyId: $ot->company_id ?? null,
        );
    }

    /** Notify when a new approval task is assigned. */
    public static function approvalPending(\App\Models\ApprovalInstance $instance, int $approverId): void
    {
        self::send(
            userId:    $approverId,
            type:      HrNotification::TYPE_APPROVAL_PENDING,
            title:     '📥 Có yêu cầu chờ bạn duyệt',
            body:      "Bạn có một yêu cầu mới cần phê duyệt.",
            priority:  HrNotification::PRIORITY_HIGH,
            actionUrl: '/approvals',
            entity:    $instance,
        );
    }

    /** Notify employees when payroll is finalized. */
    public static function payrollFinalized(\App\Models\PayrollCycle $cycle, int $userId): void
    {
        self::send(
            userId:    $userId,
            type:      HrNotification::TYPE_PAYROLL_FINALIZED,
            title:     '💰 Bảng lương đã được chốt',
            body:      "Bảng lương kỳ {$cycle->period} đã được tính toán. Xem phiếu lương của bạn.",
            actionUrl: '/self-service',
            entity:    $cycle,
            companyId: $cycle->company_id ?? null,
        );
    }

    /** Notify when a transfer is approved. */
    public static function transferApproved(\App\Models\EmployeeTransfer $transfer): void
    {
        if (! $transfer->employee?->user_id) return;

        self::send(
            userId:    $transfer->employee->user_id,
            type:      HrNotification::TYPE_TRANSFER_APPROVED,
            title:     '🔄 Quyết định điều chuyển được duyệt',
            body:      "Quyết định điều chuyển công tác của bạn đã được phê duyệt.",
            priority:  HrNotification::PRIORITY_HIGH,
            actionUrl: '/self-service',
            entity:    $transfer,
        );
    }

    // ── Scheduled checks (called by Scheduler) ────────────────────────────

    /**
     * Send alerts for contracts expiring within $daysAhead days.
     * Runs daily. Avoids duplicate by checking existing notification in last 24h.
     */
    public static function checkExpiringContracts(int $daysAhead = 30): int
    {
        $threshold = now()->addDays($daysAhead)->toDateString();
        $today     = now()->toDateString();

        $contracts = \App\Models\EmploymentContract::query()
            ->with('employee.user')
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$today, $threshold])
            ->get();

        $count = 0;
        foreach ($contracts as $contract) {
            $employee = $contract->employee;
            if (! $employee) continue;

            $daysLeft = now()->diffInDays($contract->end_date, false);
            if ($daysLeft < 0) continue;

            // Notify the employee's linked user
            if ($employee->user_id) {
                $alreadySent = HrNotification::query()
                    ->where('user_id', $employee->user_id)
                    ->where('type', HrNotification::TYPE_CONTRACT_EXPIRING)
                    ->where('entity_id', $contract->id)
                    ->where('created_at', '>=', now()->subDay())
                    ->exists();

                if (! $alreadySent) {
                    self::send(
                        userId:    $employee->user_id,
                        type:      HrNotification::TYPE_CONTRACT_EXPIRING,
                        title:     "⚠️ Hợp đồng sắp hết hạn ({$daysLeft} ngày)",
                        body:      "Hợp đồng lao động của bạn sẽ hết hạn vào {$contract->end_date}. Vui lòng liên hệ HR để gia hạn.",
                        priority:  $daysLeft <= 7 ? HrNotification::PRIORITY_URGENT : HrNotification::PRIORITY_HIGH,
                        actionUrl: '/self-service',
                        entity:    $contract,
                        companyId: $employee->company_id,
                    );
                    $count++;
                }
            }

            // Also notify HR managers of the company
            static::notifyHrManagers(
                companyId: (int) $employee->company_id,
                type:      HrNotification::TYPE_CONTRACT_EXPIRING,
                title:     "⚠️ HĐ nhân viên {$employee->full_name} sắp hết hạn ({$daysLeft} ngày)",
                body:      "Hợp đồng hết hạn ngày {$contract->end_date}. Cần gia hạn hoặc chấm dứt.",
                priority:  $daysLeft <= 7 ? HrNotification::PRIORITY_URGENT : HrNotification::PRIORITY_HIGH,
                actionUrl: '/contracts',
                entity:    $contract,
            );
            $count++;
        }

        return $count;
    }

    /**
     * Cảnh báo HĐ đã hết hạn nhưng vẫn active — chạy hàng ngày.
     */
    public static function checkExpiredContracts(): int
    {
        $today = now()->toDateString();

        $contracts = \App\Models\EmploymentContract::query()
            ->with('employee.user')
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', $today)
            ->get();

        $count = 0;
        foreach ($contracts as $contract) {
            $employee = $contract->employee;
            if (! $employee) {
                continue;
            }

            static::notifyHrManagers(
                companyId: (int) $employee->company_id,
                type: HrNotification::TYPE_CONTRACT_EXPIRED,
                title: "🚨 HĐ {$employee->full_name} đã hết hạn",
                body: "Hợp đồng {$contract->contract_number} hết hạn ngày {$contract->end_date}. Cần gia hạn hoặc chấm dứt ngay.",
                priority: HrNotification::PRIORITY_URGENT,
                actionUrl: '/contracts',
                entity: $contract,
            );
            $count++;
        }

        return $count;
    }

    /**
     * Cảnh báo OT vượt trần tháng/năm theo Điều 107 BLLĐ — chạy hàng ngày.
     */
    public static function checkOvertimeCapBreaches(): int
    {
        $period = now()->format('Y-m');
        $companies = \App\Models\Company::query()->pluck('id');
        $count = 0;

        foreach ($companies as $companyId) {
            $service = app(\App\Services\Hr\HrComplianceAlertService::class);
            $items = $service->list((int) $companyId, $period, null, 200);
            $breaches = array_filter($items, fn ($a) => in_array($a['category'], [
                'ot_monthly_exceeded',
                'ot_yearly_exceeded',
                'ot_yearly_notify_authority',
            ], true));

            if ($breaches === []) {
                continue;
            }

            $body = collect($breaches)->take(5)->pluck('message')->implode("\n");
            static::notifyHrManagers(
                companyId: (int) $companyId,
                type: HrNotification::TYPE_OT_CAP_EXCEEDED,
                title: '⚠️ OT vượt / gần vượt giới hạn pháp luật ('.count($breaches).' NV)',
                body: $body,
                priority: HrNotification::PRIORITY_HIGH,
                actionUrl: '/attendance',
            );
            $count++;
        }

        return $count;
    }

    /**
     * Send birthday greetings to employees.
     * Runs daily.
     */
    public static function checkBirthdays(): int
    {
        $today = now()->format('m-d');

        $employees = \App\Models\Employee::query()
            ->with('user')
            ->whereNotNull('date_of_birth')
            ->whereRaw("strftime('%m-%d', date_of_birth) = ?", [$today])
            ->where('employment_status', '!=', 'terminated')
            ->get();

        $count = 0;
        foreach ($employees as $employee) {
            if (! $employee->user_id) continue;

            $alreadySent = HrNotification::query()
                ->where('user_id', $employee->user_id)
                ->where('type', HrNotification::TYPE_BIRTHDAY)
                ->whereDate('created_at', today())
                ->exists();

            if (! $alreadySent) {
                self::send(
                    userId:    $employee->user_id,
                    type:      HrNotification::TYPE_BIRTHDAY,
                    title:     '🎂 Chúc mừng sinh nhật!',
                    body:      "HCM Suite chúc {$employee->full_name} sinh nhật vui vẻ và thành công rực rỡ!",
                    priority:  HrNotification::PRIORITY_LOW,
                    actionUrl: '/self-service',
                    companyId: $employee->company_id,
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Alert for probation ending within $daysAhead days.
     */
    public static function checkProbationEnding(int $daysAhead = 14): int
    {
        $threshold = now()->addDays($daysAhead)->toDateString();
        $today     = now()->toDateString();

        $employees = \App\Models\Employee::query()
            ->with('user')
            ->where('employment_status', 'probation')
            ->whereNotNull('official_start_date')
            ->whereBetween('official_start_date', [$today, $threshold])
            ->get();

        $count = 0;
        foreach ($employees as $employee) {
            if (! $employee->user_id) continue;

            $daysLeft = now()->diffInDays($employee->official_start_date, false);

            $alreadySent = HrNotification::query()
                ->where('user_id', $employee->user_id)
                ->where('type', HrNotification::TYPE_PROBATION_ENDING)
                ->where('created_at', '>=', now()->subDay())
                ->exists();

            if (! $alreadySent) {
                self::send(
                    userId:    $employee->user_id,
                    type:      HrNotification::TYPE_PROBATION_ENDING,
                    title:     "⏳ Thử việc kết thúc sau {$daysLeft} ngày",
                    body:      "Thời gian thử việc của bạn sẽ kết thúc vào {$employee->official_start_date}.",
                    priority:  HrNotification::PRIORITY_HIGH,
                    actionUrl: '/self-service',
                    companyId: $employee->company_id,
                );
                $count++;
            }

            static::notifyHrManagers(
                companyId: (int) $employee->company_id,
                type:      HrNotification::TYPE_PROBATION_ENDING,
                title:     "⏳ NV {$employee->full_name} hết thử việc sau {$daysLeft} ngày",
                body:      "Cần ra quyết định chính thức hoặc chấm dứt thử việc.",
                priority:  HrNotification::PRIORITY_HIGH,
                actionUrl: '/employees',
                entity:    $employee,
            );
            $count++;
        }

        return $count;
    }

    /** NV gửi đơn xin nghỉ việc — thông báo HR. */
    public static function resignationSubmitted(\App\Models\EmployeeTermination $termination): void
    {
        $termination->loadMissing('employee');
        $employee = $termination->employee;
        if (! $employee) {
            return;
        }

        $dateLabel = $termination->termination_date?->format('d/m/Y') ?? '—';

        static::notifyHrManagers(
            companyId: (int) $termination->company_id,
            type: HrNotification::TYPE_APPROVAL_PENDING,
            title: "📋 Đơn xin nghỉ: {$employee->full_name}",
            body: "Mã {$termination->decision_number} · Ngày dự kiến: {$dateLabel}",
            priority: HrNotification::PRIORITY_HIGH,
            actionUrl: '/offboarding',
            entity: $termination,
        );
    }

    /** HR duyệt / từ chối đơn xin nghỉ — thông báo NV. */
    public static function resignationDecision(\App\Models\EmployeeTermination $termination, string $decision): void
    {
        $userId = User::query()->where('employee_id', $termination->employee_id)->value('id');
        if (! $userId) {
            return;
        }

        $approved = $decision === 'approved';
        $dateLabel = $termination->termination_date?->format('d/m/Y') ?? '—';

        self::send(
            userId: $userId,
            type: HrNotification::TYPE_CUSTOM,
            title: $approved ? '✅ Đơn xin nghỉ đã được duyệt' : '❌ Đơn xin nghỉ bị từ chối',
            body: $approved
                ? "Ngày nghỉ việc: {$dateLabel}. HR sẽ liên hệ quy trình bàn giao."
                : (string) ($termination->rejection_reason ?? 'Vui lòng liên hệ phòng Nhân sự.'),
            priority: HrNotification::PRIORITY_HIGH,
            actionUrl: '/self-service',
            companyId: $termination->company_id,
            entity: $termination,
        );
    }

    // ── Internal helpers ──────────────────────────────────────────────────

    private static function notifyHrManagers(
        int $companyId,
        string $type,
        string $title,
        string $body,
        string $priority  = HrNotification::PRIORITY_NORMAL,
        ?string $actionUrl = null,
        mixed $entity = null,
    ): void {
        $alreadySentToday = HrNotification::query()
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->when($entity, fn ($q) => $q->where('entity_id', $entity->id ?? null))
            ->whereDate('created_at', today())
            ->exists();

        if ($alreadySentToday) return;

        $hrManagers = User::query()
            ->where('default_company_id', $companyId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'hr_manager']))
            ->pluck('id');

        foreach ($hrManagers as $managerId) {
            self::send(
                userId:    $managerId,
                type:      $type,
                title:     $title,
                body:      $body,
                priority:  $priority,
                actionUrl: $actionUrl,
                entity:    $entity,
                companyId: $companyId,
            );
        }
    }

    // ── Read helpers ──────────────────────────────────────────────────────

    public static function unreadCount(int $userId): int
    {
        return Cache::remember("notif_unread_count:{$userId}", 60, function () use ($userId) {
            return HrNotification::forUser($userId)->unread()->count();
        });
    }

    public static function markRead(int $userId, ?int $notifId = null): void
    {
        $query = HrNotification::forUser($userId)->unread();
        if ($notifId) {
            $query->where('id', $notifId);
        }
        $query->update(['read_at' => now()]);
        Cache::forget("notif_unread_count:{$userId}");
    }
}
