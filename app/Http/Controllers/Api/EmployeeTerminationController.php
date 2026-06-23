<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Models\EmployeeTermination;
use App\Services\AuditLogger;
use App\Services\Hr\EmploymentTerminationService;
use App\Services\NotificationService;
use App\Support\CompanyContext;
use App\Support\EmployeeQueryScope;
use App\Support\QuerySearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeTerminationController extends ApiController
{
    public function __construct(
        private readonly EmploymentTerminationService $terminationService,
    ) {}

    public function all(Request $request): JsonResponse
    {
        $query = EmployeeTermination::with([
            'employee:id,full_name,employee_code,department_id,position_id',
            'submittedBy:id,name,email',
        ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        EmployeeQueryScope::applyOnRelation($query, 'employee', $request);

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('decision_number', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($e) => QuerySearch::employee($e, $search));
            });
        }

        $terminations = $query->limit(300)->get()->map(function ($t) {
            $t->effective_date = $t->effective_date ?? $t->termination_date;
            $t->reason_type = $t->reason_type ?? $t->type;

            return $t;
        });

        return $this->success($terminations);
    }

    public function update(Request $request, EmployeeTermination $termination): JsonResponse
    {
        $data = $request->validate([
            'handover_tasks_done' => 'boolean',
            'assets_returned' => 'boolean',
            'exit_interview_done' => 'boolean',
            'accounts_disabled' => 'boolean',
            'final_settlement_done' => 'boolean',
            'notes' => 'nullable|string',
            'status' => 'in:pending,approved,completed,rejected',
        ]);

        $termination->update($data);

        return $this->success($termination->load('employee:id,full_name,employee_code'));
    }

    public function index(Employee $employee): JsonResponse
    {
        return $this->success($employee->terminations()->orderByDesc('termination_date')->get());
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'decision_number' => 'required|string',
            'termination_date' => 'required|date',
            'reason' => 'nullable|string',
            'type' => 'required|in:resignation,dismissal,retirement,redundancy',
            'signed_by' => 'nullable|string',
        ]);

        $termination = $employee->terminations()->create([
            ...$data,
            'company_id' => CompanyContext::id() ?? $employee->company_id,
            'reason_type' => $data['type'],
            'effective_date' => $data['termination_date'],
            'status' => 'pending',
        ]);

        AuditLogger::log('created', $termination, null, 'offboarding',
            "Tạo quyết định nghỉ việc cho NV {$employee->full_name} ({$employee->employee_code}), loại: {$data['type']}");

        return $this->success($termination, 201);
    }

    public function approve(Employee $employee, EmployeeTermination $termination): JsonResponse
    {
        $this->ensureTerminationBelongsToEmployee($employee, $termination);

        $termination = $this->terminationService->approve($termination);

        AuditLogger::log('approved', $termination, null, 'offboarding',
            "Duyệt nghỉ việc NV {$employee->full_name} ({$employee->employee_code}), hiệu lực: {$termination->termination_date}");

        NotificationService::resignationDecision($termination, 'approved');

        return $this->success($termination);
    }

    public function approveById(EmployeeTermination $termination): JsonResponse
    {
        $employee = $termination->employee;
        if (! $employee) {
            return $this->error('Không tìm thấy nhân viên.', 404);
        }

        return $this->approve($employee, $termination);
    }

    public function reject(Request $request, EmployeeTermination $termination): JsonResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $termination = $this->terminationService->reject($termination, $data['rejection_reason']);

        $employee = $termination->employee;
        AuditLogger::log('rejected', $termination, null, 'offboarding',
            "Từ chối đơn nghỉ việc NV ".($employee?->full_name ?? $termination->employee_id));

        NotificationService::resignationDecision($termination, 'rejected');

        return $this->success($termination);
    }

    protected function ensureTerminationBelongsToEmployee(Employee $employee, EmployeeTermination $termination): void
    {
        if ($termination->employee_id !== $employee->id) {
            abort(404);
        }
    }
}
