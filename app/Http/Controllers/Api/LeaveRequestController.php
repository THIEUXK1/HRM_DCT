<?php

namespace App\Http\Controllers\Api;

use App\Models\ApprovalInstance;
use App\Models\Company;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Approval\ApprovalService;
use App\Services\Attendance\LeaveDurationCalculator;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Support\EmployeeQueryScope;
use App\Support\EmployeeScopeResolver;
use App\Support\QuerySearch;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveRequestController extends ApiController
{
    public function __construct(
        private readonly LeaveDurationCalculator $durationCalculator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = LeaveRequest::with(['employee:id,full_name,employee_code,department_id', 'leaveType:id,name,is_paid,cell_symbol,code,day_count_mode']);

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

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        QuerySearch::employeeRelation($query, $request->get('search'));

        return $this->success($query->orderByDesc('created_at')->paginate($request->integer('per_page', 50)));
    }

    public function calculateDays(Request $request): JsonResponse
    {
        $data = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $totalDays = $this->durationCalculator->between($start, $end, $leaveType);

        return $this->success([
            'total_days' => $totalDays,
            'day_count_mode' => $leaveType->day_count_mode ?? 'workday',
            'mode_label' => ($leaveType->day_count_mode ?? 'workday') === 'calendar'
                ? 'Ngày dương lịch (kể cả CN, lễ)'
                : 'Ngày làm chuẩn (trừ CN và ngày lễ)',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(array_merge([
            'company_id' => 'required|exists:companies,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'total_days' => 'nullable|numeric|min:0.5',
            'reason' => 'nullable|string',
        ], EmployeeScopeResolver::bulkTargetRules()));

        $companyId = (int) $data['company_id'];
        $employees = EmployeeScopeResolver::resolve(
            $companyId,
            isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            $data['employee_ids'] ?? null,
            isset($data['department_id']) ? (int) $data['department_id'] : null,
        );

        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $totalDays = $this->durationCalculator->between($start, $end, $leaveType);
        $tenantId = Company::find($companyId)?->tenant_id;

        $created = [];
        $errors = [];

        DB::transaction(function () use ($employees, $data, $companyId, $totalDays, $tenantId, &$created, &$errors) {
            foreach ($employees as $employee) {
                try {
                    $leave = LeaveRequest::create([
                        'company_id' => $companyId,
                        'employee_id' => $employee->id,
                        'leave_type_id' => $data['leave_type_id'],
                        'start_date' => $data['start_date'],
                        'end_date' => $data['end_date'],
                        'total_days' => $totalDays,
                        'reason' => $data['reason'] ?? null,
                        'status' => 'pending',
                    ]);

                    if ($tenantId) {
                        app(ApprovalService::class)->start('leave_request', $leave->id, $tenantId);
                    }

                    $created[] = $leave->load('leaveType', 'employee:id,full_name,employee_code');
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
                'message' => 'Không tạo được đơn nghỉ cho nhân viên đã chọn.',
                'data' => ['errors' => $errors],
            ], 422);
        }

        $payload = [
            'created_count' => count($created),
            'created' => $created,
            'errors' => $errors,
        ];

        if (count($created) === 1) {
            $payload['leave'] = $created[0];
        }

        return $this->success($payload, 201);
    }

    public function approve(LeaveRequest $leaveRequest, ApprovalService $service): JsonResponse
    {
        $instance = ApprovalInstance::where('entity_type', 'leave_request')
            ->where('entity_id', $leaveRequest->id)
            ->where('status', 'pending')
            ->first();

        if ($instance) {
            $service->approve($instance, (int) auth()->id());
            AuditLogger::approved($leaveRequest, "Leave #{$leaveRequest->id} approved via workflow");
            NotificationService::leaveDecision($leaveRequest->load('employee.user'), 'approved');

            return $this->success($leaveRequest->fresh());
        }

        $leaveRequest->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        AuditLogger::approved($leaveRequest, "Leave #{$leaveRequest->id} approved directly");
        NotificationService::leaveDecision($leaveRequest->load('employee.user'), 'approved');

        return $this->success($leaveRequest);
    }
}
