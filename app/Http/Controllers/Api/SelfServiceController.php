<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\SubmitResignationRequest;
use App\Models\EmployeeReview;
use App\Models\EmployeeTermination;
use App\Models\Goal;
use App\Models\PerformanceCycle;
use App\Services\AuditLogger;
use App\Services\Hr\EmploymentTerminationService;
use App\Services\NotificationService;
use App\Services\Attendance\LeaveEntitlementService;
use App\Services\Performance\PerformanceScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfServiceController extends ApiController
{
    public function __construct(
        private readonly LeaveEntitlementService $leaveEntitlements,
    ) {}

    public function profile(): JsonResponse
    {
        $user = auth()->user();
        $employee = $user?->employee?->load([
            'company',
            'department',
            'position',
            'branch',
            'profile',
            'dependents' => fn ($q) => $q->where('is_active', true)->orderBy('id'),
        ]);

        if (! $employee) {
            return $this->error('No employee profile linked to this user.', 404);
        }

        return $this->success($employee);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = auth()->user();
        $employee = $user?->employee;

        if (! $employee) {
            return $this->error('No employee profile linked to this user.', 404);
        }

        $data = $request->validate([
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'personal_email' => 'nullable|email|max:255',
            'emergency_contact_phone' => 'nullable|string|max:30',
        ]);

        $employee->update(collect($data)->only(['phone', 'address', 'city', 'personal_email'])->all());

        if (array_key_exists('emergency_contact_phone', $data)) {
            $employee->profile()->updateOrCreate(
                ['employee_id' => $employee->id],
                ['emergency_contact_phone' => $data['emergency_contact_phone']]
            );
        }

        $employee->load(['company', 'department', 'position', 'branch', 'profile', 'dependents']);

        return $this->success($employee);
    }

    public function contracts(): JsonResponse
    {
        $employee = auth()->user()?->employee;

        if (! $employee) {
            return $this->error('No employee profile linked to this user.', 404);
        }

        return $this->success($employee->contracts()->orderByDesc('start_date')->limit(20)->get());
    }

    public function payslips(): JsonResponse
    {
        $employee = auth()->user()?->employee;

        if (! $employee) {
            return $this->error('No employee profile linked to this user.', 404);
        }

        $results = \App\Models\PayrollResult::with('cycle', 'payslip')
            ->where('employee_id', $employee->id)
            ->orderByDesc('id')
            ->limit(36)
            ->get();

        return $this->success($results);
    }

    public function leaveRequests(): JsonResponse
    {
        $employee = auth()->user()?->employee;
        if (! $employee) {
            return $this->error('No employee profile linked to this user.', 404);
        }

        $leaves = \App\Models\LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->orderByDesc('start_date')
            ->limit(100)
            ->get();

        return $this->success($leaves);
    }

    public function leaveBalance(Request $request): JsonResponse
    {
        $employee = auth()->user()?->employee;
        if (! $employee) {
            return $this->error('No employee profile linked to this user.', 404);
        }

        $employee->load(['department:id,name,leave_entitlement_group_id', 'leaveEntitlementGroup:id,code,name,annual_days']);
        $year = (int) $request->integer('year', (int) now()->format('Y'));

        return $this->success($this->leaveEntitlements->balanceForEmployee($employee, $year));
    }

    public function attendanceSummary(): JsonResponse
    {
        $employee = auth()->user()?->employee;
        if (! $employee) {
            return $this->error('No employee profile linked to this user.', 404);
        }

        $summaries = \App\Models\AttendanceSummary::where('employee_id', $employee->id)
            ->orderByDesc('period')
            ->take(12)
            ->get();

        return $this->success($summaries);
    }

    public function resignationRequests(): JsonResponse
    {
        $employee = auth()->user()?->employee;
        if (! $employee) {
            return $this->error('Tài khoản chưa liên kết hồ sơ nhân viên.', 404);
        }

        $items = EmployeeTermination::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($t) {
                $t->effective_date = $t->effective_date ?? $t->termination_date;
                $t->reason_type = $t->reason_type ?? $t->type;

                return $t;
            });

        $noticeDays = (int) config('hr_vn.resignation.notice_days_default', 30);

        return $this->success([
            'requests' => $items,
            'notice_days_hint' => $noticeDays,
            'can_submit' => $employee->is_active
                && $employee->employment_status !== 'terminated'
                && ! $items->contains('status', 'pending'),
        ]);
    }

    public function storeResignationRequest(
        SubmitResignationRequest $request,
        EmploymentTerminationService $terminationService,
    ): JsonResponse {
        $user = auth()->user();
        $employee = $user?->employee;

        if (! $employee) {
            return $this->error('Tài khoản chưa liên kết hồ sơ nhân viên.', 404);
        }

        $termination = $terminationService->submitEmployeeResignation($employee, $user, $request->validated());

        AuditLogger::log('created', $termination, null, 'offboarding',
            "NV {$employee->full_name} gửi đơn xin nghỉ việc, ngày dự kiến: {$termination->termination_date}");

        NotificationService::resignationSubmitted($termination);

        return $this->success($termination, 201);
    }

    public function cancelResignationRequest(
        EmployeeTermination $termination,
        EmploymentTerminationService $terminationService,
    ): JsonResponse {
        $employee = auth()->user()?->employee;
        if (! $employee) {
            return $this->error('Tài khoản chưa liên kết hồ sơ nhân viên.', 404);
        }

        $terminationService->cancelByEmployee($termination, $employee);

        return $this->success(['message' => 'Đã hủy đơn xin nghỉ việc.']);
    }

    public function myKpi(PerformanceScoreService $scoreService): JsonResponse
    {
        $employee = auth()->user()?->employee?->loadMissing('company');
        if (! $employee) {
            return $this->error('No employee profile linked to this user.', 404);
        }

        $cycle = PerformanceCycle::query()
            ->where('tenant_id', $employee->company?->tenant_id)
            ->orderByDesc('period')
            ->first();

        if (! $cycle) {
            return $this->success(['cycle' => null, 'goals' => [], 'kpi_score' => null, 'review' => null]);
        }

        $goals = Goal::query()
            ->where('employee_id', $employee->id)
            ->where('performance_cycle_id', $cycle->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('id')
            ->get();

        $review = EmployeeReview::query()
            ->where('employee_id', $employee->id)
            ->where('performance_cycle_id', $cycle->id)
            ->first();

        return $this->success([
            'cycle' => $cycle->only(['id', 'name', 'period', 'status']),
            'goals' => $goals,
            'kpi_score' => $scoreService->employeeKpiScore($employee->id, $cycle->id),
            'review' => $review,
        ]);
    }
}
