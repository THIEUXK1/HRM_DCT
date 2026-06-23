<?php

namespace App\Http\Controllers\Api;

use App\Services\Attendance\AttendancePeriodLockService;
use App\Services\Attendance\AttendanceSummaryService;
use App\Support\CompanyContext;
use App\Support\EmployeeQueryScope;
use App\Support\QuerySearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceSummaryController extends ApiController
{
    public function __construct(
        private readonly AttendancePeriodLockService $periodLock,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = (int) ($request->input('company_id') ?? CompanyContext::id());
        $period = $request->input('period') ?? now()->format('Y-m');

        $query = \App\Models\AttendanceSummary::with(['employee:id,full_name,employee_code,department_id', 'employee.department:id,name'])
            ->where('company_id', $companyId)
            ->where('period', $period);

        $user = auth()->user();
        if ($user && ! $user->hasAnyRole(['admin', 'hr_manager', 'auditor'])) {
            $employee = $user->employee;
            if ($employee && $employee->department_id) {
                $query->whereHas('employee', function ($q) use ($employee) {
                    $q->where('department_id', $employee->department_id);
                });
            }
        }

        EmployeeQueryScope::applyOnRelation($query, 'employee', $request);

        QuerySearch::employeeRelation($query, $request->get('search'));

        return $this->success([
            'period_status' => $this->periodLock->status($companyId, $period),
            'summaries' => $query->limit(500)->get(),
        ]);
    }

    public function periodStatus(Request $request): JsonResponse
    {
        $companyId = (int) ($request->input('company_id') ?? CompanyContext::id());
        $period = $request->validate(['period' => 'required|regex:/^\d{4}-\d{2}$/'])['period'];

        return $this->success($this->periodLock->status($companyId, $period));
    }

    public function lock(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'period' => 'required|regex:/^\d{4}-\d{2}$/',
            'notes' => 'nullable|string|max:500',
        ]);

        $result = $this->periodLock->lock(
            (int) $data['company_id'],
            $data['period'],
            auth()->user(),
            $data['notes'] ?? null,
        );

        return $this->success($result);
    }

    public function unlock(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'period' => 'required|regex:/^\d{4}-\d{2}$/',
            'reason' => 'nullable|string|max:500',
        ]);

        $result = $this->periodLock->unlock(
            (int) $data['company_id'],
            $data['period'],
            auth()->user(),
            $data['reason'] ?? null,
        );

        return $this->success($result);
    }

    public function build(Request $request, AttendanceSummaryService $service): JsonResponse
    {
        $companyId = (int) ($request->input('company_id') ?? CompanyContext::id());
        $period = $request->validate(['period' => 'required|regex:/^\d{4}-\d{2}$/'])['period'];

        $count = $service->buildForPeriod($companyId, $period);

        return $this->success(['built' => $count, 'period' => $period]);
    }
}
