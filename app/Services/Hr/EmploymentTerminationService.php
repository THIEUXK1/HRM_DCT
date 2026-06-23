<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeTermination;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmploymentTerminationService
{
    public function submitEmployeeResignation(Employee $employee, User $user, array $data): EmployeeTermination
    {
        if ($employee->employment_status === 'terminated' || ! $employee->is_active) {
            throw ValidationException::withMessages([
                'employee' => ['Bạn không thể gửi đơn xin nghỉ khi đã nghỉ việc.'],
            ]);
        }

        $pending = EmployeeTermination::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'termination_date' => ['Bạn đã có đơn xin nghỉ đang chờ duyệt.'],
            ]);
        }

        $terminationDate = $data['termination_date'];

        return EmployeeTermination::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'submitted_by_user_id' => $user->id,
            'requested_at' => now(),
            'decision_number' => $this->generateDecisionNumber($employee, 'XN'),
            'termination_date' => $terminationDate,
            'effective_date' => $terminationDate,
            'reason' => $data['reason'],
            'type' => 'resignation',
            'reason_type' => 'resignation',
            'notice_period_days' => $data['notice_period_days'] ?? null,
            'handover_note' => $data['handover_note'] ?? null,
            'status' => 'pending',
        ]);
    }

    public function generateDecisionNumber(Employee $employee, string $prefix = 'QĐ'): string
    {
        $code = preg_replace('/\s+/', '', (string) $employee->employee_code) ?: (string) $employee->id;
        $candidate = sprintf('%s-%s-%s', $prefix, $code, now()->format('Ymd'));
        $suffix = 1;

        while (EmployeeTermination::where('decision_number', $candidate)->exists()) {
            $candidate = sprintf('%s-%s-%s-%d', $prefix, $code, now()->format('Ymd'), $suffix);
            $suffix++;
        }

        return $candidate;
    }

    public function approve(EmployeeTermination $termination): EmployeeTermination
    {
        if ($termination->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Quyết định này đã được xử lý.'],
            ]);
        }

        $employee = $termination->employee;
        if (! $employee) {
            throw ValidationException::withMessages([
                'employee' => ['Không tìm thấy nhân viên.'],
            ]);
        }

        $termination->update(['status' => 'approved']);

        $employee->update([
            'employment_status' => 'terminated',
            'termination_date' => $termination->termination_date,
            'termination_reason' => $termination->reason,
            'is_active' => false,
        ]);

        return $termination->fresh(['employee:id,full_name,employee_code,department_id']);
    }

    public function reject(EmployeeTermination $termination, string $reason): EmployeeTermination
    {
        if ($termination->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Quyết định này đã được xử lý.'],
            ]);
        }

        $termination->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        return $termination->fresh(['employee:id,full_name,employee_code']);
    }

    public function cancelByEmployee(EmployeeTermination $termination, Employee $employee): void
    {
        if ($termination->employee_id !== $employee->id) {
            abort(403);
        }

        if ($termination->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Chỉ có thể hủy đơn đang chờ duyệt.'],
            ]);
        }

        $termination->delete();
    }
}
