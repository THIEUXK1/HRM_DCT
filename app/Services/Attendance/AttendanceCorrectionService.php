<?php

namespace App\Services\Attendance;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceLog;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceCorrectionService
{
    public function create(array $data): AttendanceCorrectionRequest
    {
        return AttendanceCorrectionRequest::create($data + ['status' => 'pending']);
    }

    public function approve(AttendanceCorrectionRequest $request, int $approverId): AttendanceCorrectionRequest
    {
        return DB::transaction(function () use ($request, $approverId) {
            $request->update([
                'status' => 'approved',
                'approved_by' => $approverId,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->applyToAttendanceLog($request->fresh(['reason']));

            $period = Carbon::parse($request->work_date)->format('Y-m');
            $locked = AttendanceSummary::where('employee_id', $request->employee_id)
                ->where('period', $period)
                ->where('is_locked', true)
                ->exists();

            if (! $locked) {
                app(AttendanceSummaryService::class)->rebuildEmployeePeriod(
                    (int) $request->employee_id,
                    (int) $request->company_id,
                    $period,
                );
            }

            return $request->fresh(['employee', 'reason']);
        });
    }

    public function reject(AttendanceCorrectionRequest $request, int $approverId, ?string $reason = null): AttendanceCorrectionRequest
    {
        $request->update([
            'status' => 'rejected',
            'approved_by' => $approverId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $request->fresh(['employee', 'reason']);
    }

    public function applyToAttendanceLog(AttendanceCorrectionRequest $request): AttendanceLog
    {
        $log = AttendanceLog::firstOrNew([
            'employee_id' => $request->employee_id,
            'work_date' => $request->work_date->toDateString(),
        ]);

        if (! $log->exists) {
            $log->company_id = $request->company_id;
            $log->source = 'correction';
        }

        if ($request->requested_check_in_at) {
            $log->check_in_at = $request->requested_check_in_at;
        }

        if ($request->requested_check_out_at) {
            $log->check_out_at = $request->requested_check_out_at;
        }

        $log->save();

        return $log;
    }

    /**
     * @return array{forgot_punch_count: int, correction_approved_count: int}
     */
    public function countsForEmployeePeriod(int $employeeId, Carbon $start, Carbon $end): array
    {
        $approved = AttendanceCorrectionRequest::with('reason:id,counts_as_forgot_punch')
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $forgot = $approved->filter(fn ($r) => (bool) $r->reason?->counts_as_forgot_punch)->count();

        return [
            'forgot_punch_count' => $forgot,
            'correction_approved_count' => $approved->count(),
        ];
    }
}
