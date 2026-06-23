<?php

namespace App\Http\Controllers\Api;

use App\Models\AttendanceCorrectionRequest;
use App\Services\Attendance\AttendanceCorrectionService;
use App\Services\AuditLogger;
use App\Support\EmployeeQueryScope;
use App\Support\EmployeeScopeResolver;
use App\Support\QuerySearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceCorrectionRequestController extends ApiController
{
    public function __construct(
        private readonly AttendanceCorrectionService $correctionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = AttendanceCorrectionRequest::with([
            'employee:id,full_name,employee_code,department_id',
            'employee.department:id,name',
            'reason:id,code,name,counts_as_forgot_punch',
        ]);

        $user = auth()->user();
        if ($user && ! $user->hasAnyRole(['admin', 'hr_manager', 'auditor'])) {
            $employee = $user->employee;
            if ($employee) {
                $query->where('employee_id', $employee->id);
            }
        }

        EmployeeQueryScope::applyOnRelation($query, 'employee', $request);

        if ($period = $request->string('period')->toString()) {
            $start = \Carbon\Carbon::createFromFormat('Y-m', $period)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $query->whereBetween('work_date', [$start->toDateString(), $end->toDateString()]);
        }

        QuerySearch::employeeRelation($query, $request->get('search'));

        return $this->success(
            $query->orderByDesc('work_date')->paginate($request->integer('per_page', 50))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(array_merge([
            'company_id' => 'required|exists:companies,id',
            'correction_reason_id' => 'required|exists:attendance_correction_reasons,id',
            'correction_mode' => 'nullable|in:check_in,check_out,both',
            'work_date' => 'required|date',
            'requested_check_in_at' => 'nullable|date',
            'requested_check_out_at' => 'nullable|date',
            'note' => 'nullable|string|max:1000',
        ], EmployeeScopeResolver::bulkTargetRules()));

        $mode = $data['correction_mode'] ?? 'both';
        $hasCheckIn = ! empty($data['requested_check_in_at']);
        $hasCheckOut = ! empty($data['requested_check_out_at']);

        if (($mode === 'check_in' || $mode === 'both') && ! $hasCheckIn) {
            return $this->error('Vui lòng nhập giờ vào cần bù.', 422);
        }
        if (($mode === 'check_out' || $mode === 'both') && ! $hasCheckOut) {
            return $this->error('Vui lòng nhập giờ ra cần bù.', 422);
        }
        if (! $hasCheckIn && ! $hasCheckOut) {
            return $this->error('Đơn bù thẻ cần có ít nhất giờ vào hoặc giờ ra.', 422);
        }
        if ($hasCheckIn && $hasCheckOut && strtotime($data['requested_check_out_at']) < strtotime($data['requested_check_in_at'])) {
            return $this->error('Giờ ra phải lớn hơn hoặc bằng giờ vào.', 422);
        }

        $companyId = (int) $data['company_id'];
        $employees = EmployeeScopeResolver::resolve(
            $companyId,
            isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            $data['employee_ids'] ?? null,
            isset($data['department_id']) ? (int) $data['department_id'] : null,
        );

        $created = [];
        $errors = [];

        DB::transaction(function () use ($employees, $data, $companyId, &$created, &$errors) {
            foreach ($employees as $employee) {
                try {
                    $payload = array_merge($data, [
                        'company_id' => $companyId,
                        'employee_id' => $employee->id,
                    ]);
                    $correction = $this->correctionService->create($payload);
                    $created[] = $correction->load(['employee:id,full_name,employee_code', 'reason:id,name,code']);
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
                'message' => 'Không tạo được đơn bù thẻ.',
                'data' => ['errors' => $errors],
            ], 422);
        }

        return $this->success([
            'created_count' => count($created),
            'created' => $created,
            'errors' => $errors,
        ], 201);
    }

    public function approve(AttendanceCorrectionRequest $attendanceCorrectionRequest): JsonResponse
    {
        if ($attendanceCorrectionRequest->status !== 'pending') {
            return $this->error('Đơn bù thẻ không ở trạng thái chờ duyệt.', 422);
        }

        $updated = $this->correctionService->approve($attendanceCorrectionRequest, (int) auth()->id());
        AuditLogger::approved($updated, "Duyệt bù thẻ NV #{$updated->employee_id} ngày {$updated->work_date->format('Y-m-d')}");

        return $this->success($updated);
    }

    public function reject(Request $request, AttendanceCorrectionRequest $attendanceCorrectionRequest): JsonResponse
    {
        if ($attendanceCorrectionRequest->status !== 'pending') {
            return $this->error('Đơn bù thẻ không ở trạng thái chờ duyệt.', 422);
        }

        $data = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $updated = $this->correctionService->reject(
            $attendanceCorrectionRequest,
            (int) auth()->id(),
            $data['rejection_reason'] ?? null,
        );

        return $this->success($updated);
    }
}
