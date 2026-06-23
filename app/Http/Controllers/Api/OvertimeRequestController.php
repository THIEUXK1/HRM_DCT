<?php

namespace App\Http\Controllers\Api;

use App\Models\ApprovalInstance;
use App\Models\Company;
use App\Models\OvertimeRequest;
use App\Services\Approval\ApprovalService;
use App\Services\AuditLogger;
use App\Services\Attendance\OvertimeCapValidator;
use App\Services\Attendance\OvertimeExcessService;
use App\Services\Attendance\VietnamHolidayService;
use App\Services\NotificationService;
use App\Support\EmployeeQueryScope;
use App\Support\EmployeeScopeResolver;
use App\Support\QuerySearch;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OvertimeRequestController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = OvertimeRequest::with('employee:id,full_name,employee_code,department_id');

        $user = auth()->user();
        if ($user && !$user->hasAnyRole(['admin', 'hr_manager', 'auditor'])) {
            $employee = $user->employee;
            if ($employee && $employee->department_id) {
                $query->whereHas('employee', function ($q) use ($employee) {
                    $q->where('department_id', $employee->department_id);
                });
            }
        }

        EmployeeQueryScope::applyOnRelation($query, 'employee', $request);

        QuerySearch::employeeRelation($query, $request->get('search'));

        return $this->success($query->orderByDesc('created_at')->paginate($request->integer('per_page', 50)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(array_merge([
            'company_id' => 'required|exists:companies,id',
            'work_date' => 'required|date',
            'hours' => 'required|numeric|min:0.5|max:4',
            'night_hours' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string',
            'ot_type' => 'nullable|in:weekday,weekend,holiday',
        ], EmployeeScopeResolver::bulkTargetRules()));

        $companyId = (int) $data['company_id'];
        $employees = EmployeeScopeResolver::resolve(
            $companyId,
            isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            $data['employee_ids'] ?? null,
            isset($data['department_id']) ? (int) $data['department_id'] : null,
        );

        $workDate = Carbon::parse($data['work_date']);
        $defaultOtType = VietnamHolidayService::isHoliday($workDate)
            ? 'holiday'
            : ($workDate->isWeekend() ? 'weekend' : 'weekday');

        $tenantId = Company::find($companyId)?->tenant_id;
        $created = [];
        $errors = [];
        $warnings = [];

        DB::transaction(function () use ($employees, $data, $companyId, $request, $defaultOtType, $tenantId, &$created, &$errors, &$warnings) {
            foreach ($employees as $employee) {
                try {
                    $capCheck = OvertimeCapValidator::validate(
                        $employee->id,
                        $data['work_date'],
                        (float) $data['hours'],
                    );

                    $ot = OvertimeRequest::create([
                        'company_id' => $companyId,
                        'employee_id' => $employee->id,
                        'work_date' => $data['work_date'],
                        'hours' => $data['hours'],
                        'night_hours' => $data['night_hours'] ?? 0,
                        'reason' => $data['reason'] ?? null,
                        'status' => 'pending',
                        'ot_type' => $request->input('ot_type', $defaultOtType),
                        'exceeds_daily_cap' => ! $capCheck['daily_ok'],
                        'exceeds_monthly_cap' => ! $capCheck['monthly_ok'],
                    ]);

                    if (! $capCheck['valid']) {
                        app(OvertimeExcessService::class)->syncFromCapCheck($ot, $capCheck);
                        $warnings[] = [
                            'employee_id' => $employee->id,
                            'warnings' => $capCheck['warnings'],
                        ];
                    }

                    if ($tenantId) {
                        app(ApprovalService::class)->start('overtime_request', $ot->id, $tenantId);
                    }

                    $created[] = $ot->load('employee:id,full_name,employee_code');
                } catch (\Throwable $e) {
                    $errors[] = [
                        'employee_id' => $employee->id,
                        'employee_code' => $employee->employee_code,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        });

        if ($created === [] && $errors !== []) {
            return response()->json([
                'message' => 'Không tạo được đơn tăng ca.',
                'data' => ['errors' => $errors],
            ], 422);
        }

        $payload = [
            'created_count' => count($created),
            'created' => $created,
            'errors' => $errors,
            'warnings' => $warnings,
        ];

        if (count($created) === 1) {
            $payload['ot'] = $created[0];
        }

        return $this->success($payload, 201);
    }

    /** Tra cứu tình trạng OT của nhân viên (để hiện warning trên form) */
    public function capSummary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'period'      => 'required|regex:/^\d{4}-\d{2}$/',
        ]);

        return $this->success(
            OvertimeCapValidator::summary((int) $data['employee_id'], $data['period'])
        );
    }

    public function approve(OvertimeRequest $overtimeRequest, ApprovalService $service): JsonResponse
    {
        $instance = ApprovalInstance::where('entity_type', 'overtime_request')
            ->where('entity_id', $overtimeRequest->id)
            ->where('status', 'pending')
            ->first();

        if ($instance) {
            $service->approve($instance, (int) auth()->id());
            AuditLogger::approved($overtimeRequest, "OT #{$overtimeRequest->id} approved via workflow");
            NotificationService::otDecision($overtimeRequest->load('employee.user'), 'approved');

            return $this->success($overtimeRequest->fresh());
        }

        $overtimeRequest->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        AuditLogger::approved($overtimeRequest, "OT #{$overtimeRequest->id} approved directly");
        NotificationService::otDecision($overtimeRequest->load('employee.user'), 'approved');

        return $this->success($overtimeRequest);
    }
}
