<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreEmployeeWorkScheduleRequest;
use App\Http\Requests\StoreWorkScheduleGroupRequest;
use App\Http\Requests\StoreWorkSchedulePatternRequest;
use App\Models\Employee;
use App\Models\EmployeeWorkSchedule;
use App\Models\WorkScheduleGroup;
use App\Models\WorkSchedulePattern;
use App\Services\Attendance\OvertimeExcessService;
use App\Services\Attendance\WorkScheduleBulkAssignService;
use App\Services\Attendance\WorkScheduleComplianceService;
use App\Services\Attendance\WorkScheduleSetupService;
use App\Models\WorkScheduleWeekOverride;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkScheduleController extends ApiController
{
    public function __construct(
        private readonly WorkScheduleSetupService $setupService,
        private readonly WorkScheduleComplianceService $complianceService,
        private readonly OvertimeExcessService $excessService,
        private readonly WorkScheduleBulkAssignService $bulkAssignService,
    ) {}

    public function config(): JsonResponse
    {
        return $this->success([
            'group_types' => config('work_schedule_vn.group_types', []),
            'pattern_presets' => config('work_schedule_vn.pattern_presets', []),
            'max_consecutive_work_days' => config('work_schedule_vn.max_consecutive_work_days', 13),
            'alert_types' => config('work_schedule_vn.alert_types', []),
        ]);
    }

    public function seedDefaults(): JsonResponse
    {
        $companyId = (int) CompanyContext::id();
        $result = $this->setupService->seedDefaults($companyId);

        return $this->success([
            'seeded' => $result,
            'groups' => WorkScheduleGroup::where('company_id', $companyId)->with('patterns')->get(),
        ]);
    }

    public function indexGroups(): JsonResponse
    {
        $companyId = (int) CompanyContext::id();

        return $this->success(
            WorkScheduleGroup::where('company_id', $companyId)
                ->withCount('patterns')
                ->orderBy('code')
                ->get(),
        );
    }

    public function storeGroup(StoreWorkScheduleGroupRequest $request): JsonResponse
    {
        $companyId = (int) CompanyContext::id();
        $group = WorkScheduleGroup::create($request->validated() + ['company_id' => $companyId]);

        return $this->success($group, 201);
    }

    public function updateGroup(StoreWorkScheduleGroupRequest $request, WorkScheduleGroup $workScheduleGroup): JsonResponse
    {
        $workScheduleGroup->update($request->validated());

        return $this->success($workScheduleGroup);
    }

    public function indexPatterns(Request $request): JsonResponse
    {
        $companyId = (int) CompanyContext::id();
        $query = WorkSchedulePattern::where('company_id', $companyId)
            ->with(['group:id,code,name,group_type', 'workShift:id,code,name']);

        if ($request->filled('group_id')) {
            $query->where('work_schedule_group_id', $request->integer('group_id'));
        }

        return $this->success($query->orderBy('code')->get());
    }

    public function storePattern(StoreWorkSchedulePatternRequest $request): JsonResponse
    {
        $companyId = (int) CompanyContext::id();
        $pattern = WorkSchedulePattern::create($request->validated() + ['company_id' => $companyId]);

        return $this->success($pattern->load(['group', 'workShift']), 201);
    }

    public function updatePattern(StoreWorkSchedulePatternRequest $request, WorkSchedulePattern $workSchedulePattern): JsonResponse
    {
        $workSchedulePattern->update($request->validated());

        return $this->success($workSchedulePattern->fresh(['group', 'workShift']));
    }

    public function indexAssignments(Request $request): JsonResponse
    {
        $companyId = (int) CompanyContext::id();
        $query = EmployeeWorkSchedule::with([
            'employee:id,employee_code,full_name,department_id',
            'employee.department:id,name',
            'group:id,code,name,group_type',
            'pattern:id,code,name,pattern_code,hours_per_day',
        ])->where('company_id', $companyId);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        return $this->success($query->orderByDesc('effective_from')->paginate(50));
    }

    public function storeAssignment(StoreEmployeeWorkScheduleRequest $request): JsonResponse
    {
        $companyId = (int) CompanyContext::id();
        $data = $request->validated();

        Employee::where('company_id', $companyId)->findOrFail($data['employee_id']);

        $assignment = EmployeeWorkSchedule::create($data + ['company_id' => $companyId]);

        return $this->success($assignment->load(['employee', 'group', 'pattern']), 201);
    }

    public function updateAssignment(StoreEmployeeWorkScheduleRequest $request, EmployeeWorkSchedule $employeeWorkSchedule): JsonResponse
    {
        $employeeWorkSchedule->update($request->validated());

        return $this->success($employeeWorkSchedule->fresh(['employee', 'group', 'pattern']));
    }

    public function complianceAlerts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $companyId = (int) CompanyContext::id();

        return $this->success([
            'period' => $validated['period'],
            'alerts' => $this->complianceService->listCompanyAlerts($companyId, $validated['period']),
        ]);
    }

    public function overtimeExcess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $companyId = (int) CompanyContext::id();

        return $this->success([
            'period' => $validated['period'],
            'records' => $this->excessService->listForCompanyPeriod($companyId, $validated['period']),
        ]);
    }

    public function bulkAssign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Old fields (backward compatibility)
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'work_schedule_group_id' => ['nullable', 'integer', 'exists:work_schedule_groups,id'],
            'work_schedule_pattern_id' => ['nullable', 'integer', 'exists:work_schedule_patterns,id'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'weekend_swap_enabled' => ['sometimes', 'boolean'],

            // New fields
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'shift_id' => ['nullable', 'integer', 'exists:work_schedule_patterns,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'swap_weekend' => ['sometimes', 'boolean'],
        ]);

        $effectiveFrom = $validated['start_date'] ?? $validated['effective_from'] ?? null;
        $effectiveTo = $validated['end_date'] ?? $validated['effective_to'] ?? null;
        $patternId = $validated['shift_id'] ?? $validated['work_schedule_pattern_id'] ?? null;

        if (!$effectiveFrom || !$patternId) {
            return response()->json([
                'message' => 'Các thông tin ngày bắt đầu và ca làm việc là bắt buộc.',
                'errors' => [
                    'start_date' => ['Trường ngày bắt đầu (start_date/effective_from) là bắt buộc.'],
                    'shift_id' => ['Trường ca làm việc (shift_id/work_schedule_pattern_id) là bắt buộc.']
                ]
            ], 422);
        }

        $groupId = $validated['work_schedule_group_id'] ?? null;
        if (!$groupId && $patternId) {
            $groupId = \App\Models\WorkSchedulePattern::where('id', $patternId)->value('work_schedule_group_id');
        }

        if (!$groupId) {
            return response()->json([
                'message' => 'Không tìm thấy nhóm lịch làm việc hợp lệ cho ca làm việc này.',
                'errors' => [
                    'work_schedule_group_id' => ['Không tìm thấy nhóm ca.']
                ]
            ], 422);
        }

        $weekendSwap = $validated['swap_weekend'] ?? $validated['weekend_swap_enabled'] ?? false;

        $departmentIds = $validated['department_ids'] ?? [];
        if (isset($validated['department_id'])) {
            $departmentIds[] = (int) $validated['department_id'];
        }
        $departmentIds = array_values(array_unique($departmentIds));

        $employeeIds = $validated['employee_ids'] ?? [];

        if (empty($departmentIds) && empty($employeeIds)) {
            return response()->json([
                'message' => 'Vui lòng chọn ít nhất một phòng ban hoặc nhân viên.',
                'errors' => [
                    'department_ids' => ['Danh sách phòng ban hoặc nhân viên không được để trống.'],
                    'employee_ids' => ['Danh sách phòng ban hoặc nhân viên không được để trống.']
                ]
            ], 422);
        }

        $companyId = (int) CompanyContext::id();
        $result = $this->bulkAssignService->assignBulk(
            $companyId,
            $departmentIds,
            $employeeIds,
            (int) $groupId,
            (int) $patternId,
            $effectiveFrom,
            $effectiveTo,
            (bool) $weekendSwap
        );

        return $this->success($result, 201);
    }

    public function indexWeekOverrides(Request $request): JsonResponse
    {
        $companyId = (int) CompanyContext::id();
        $query = WorkScheduleWeekOverride::with('employee:id,employee_code,full_name')
            ->where('company_id', $companyId);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        return $this->success($query->orderByDesc('week_start')->limit(100)->get());
    }

    public function storeWeekOverride(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'week_start' => ['required', 'date'],
            'swap_enabled' => ['sometimes', 'boolean'],
            'swap_rest_day' => ['nullable', 'integer', 'min:1', 'max:7'],
            'swap_work_day' => ['nullable', 'integer', 'min:1', 'max:7'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $companyId = (int) CompanyContext::id();
        Employee::where('company_id', $companyId)->findOrFail($data['employee_id']);

        $weekStart = \Carbon\Carbon::parse($data['week_start'])->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();

        $override = WorkScheduleWeekOverride::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'week_start' => $weekStart],
            [
                'company_id' => $companyId,
                'swap_enabled' => (bool) ($data['swap_enabled'] ?? true),
                'swap_rest_day' => (int) ($data['swap_rest_day'] ?? 6),
                'swap_work_day' => (int) ($data['swap_work_day'] ?? 7),
                'notes' => $data['notes'] ?? null,
            ],
        );

        return $this->success($override->load('employee:id,employee_code,full_name'), 201);
    }
}
