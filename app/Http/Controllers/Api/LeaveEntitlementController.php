<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\AssignLeaveEntitlementGroupRequest;
use App\Http\Requests\StoreLeaveEntitlementGroupRequest;
use App\Http\Requests\UpdateLeaveEntitlementGroupRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveEntitlementGroup;
use App\Services\Attendance\LeaveEntitlementService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveEntitlementController extends ApiController
{
    public function __construct(
        private readonly LeaveEntitlementService $entitlements,
    ) {}

    public function indexGroups(): JsonResponse
    {
        $companyId = $this->entitlements->companyIdFromContext();
        $this->entitlements->ensureDefaultGroups($companyId);

        $groups = LeaveEntitlementGroup::query()
            ->where('company_id', $companyId)
            ->withCount('employees')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->success($groups);
    }

    public function storeGroup(StoreLeaveEntitlementGroupRequest $request): JsonResponse
    {
        $companyId = $this->entitlements->companyIdFromContext();
        $data = $request->validated();
        $data['company_id'] = $companyId;
        $data['code'] = strtoupper($data['code']);

        $group = DB::transaction(function () use ($data, $companyId) {
            if (! empty($data['is_default'])) {
                LeaveEntitlementGroup::where('company_id', $companyId)->update(['is_default' => false]);
            }

            return LeaveEntitlementGroup::create($data);
        });

        return $this->success($group, 201);
    }

    public function updateGroup(UpdateLeaveEntitlementGroupRequest $request, LeaveEntitlementGroup $leaveEntitlementGroup): JsonResponse
    {
        $this->assertGroupCompany($leaveEntitlementGroup);
        $data = $request->validated();

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        DB::transaction(function () use ($data, $leaveEntitlementGroup) {
            if (! empty($data['is_default'])) {
                LeaveEntitlementGroup::where('company_id', $leaveEntitlementGroup->company_id)
                    ->where('id', '!=', $leaveEntitlementGroup->id)
                    ->update(['is_default' => false]);
            }
            $leaveEntitlementGroup->update($data);
        });

        return $this->success($leaveEntitlementGroup->fresh());
    }

    public function destroyGroup(LeaveEntitlementGroup $leaveEntitlementGroup): JsonResponse
    {
        $this->assertGroupCompany($leaveEntitlementGroup);

        if ($leaveEntitlementGroup->is_default) {
            return $this->error('Không thể xóa nhóm phép mặc định. Hãy đặt nhóm khác làm mặc định trước.', 422);
        }

        $inUse = Employee::where('leave_entitlement_group_id', $leaveEntitlementGroup->id)->exists()
            || Department::where('leave_entitlement_group_id', $leaveEntitlementGroup->id)->exists();

        if ($inUse) {
            return $this->error('Nhóm đang được gán cho nhân viên hoặc phòng ban.', 422);
        }

        $leaveEntitlementGroup->delete();

        return $this->noContent();
    }

    public function assignEmployees(
        AssignLeaveEntitlementGroupRequest $request,
        LeaveEntitlementGroup $leaveEntitlementGroup,
    ): JsonResponse {
        $this->assertGroupCompany($leaveEntitlementGroup);
        $companyId = $leaveEntitlementGroup->company_id;
        $ids = $request->validated('employee_ids');
        $clearOverride = (bool) $request->boolean('clear_override');

        $payload = ['leave_entitlement_group_id' => $leaveEntitlementGroup->id];
        if ($clearOverride) {
            $payload['annual_leave_days_override'] = null;
        }

        $updated = Employee::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->update($payload);

        return $this->success(['updated' => $updated]);
    }

    public function assignDepartment(Request $request, Department $department): JsonResponse
    {
        $data = $request->validate([
            'leave_entitlement_group_id' => ['nullable', 'exists:leave_entitlement_groups,id'],
        ]);

        if ($data['leave_entitlement_group_id'] ?? null) {
            $group = LeaveEntitlementGroup::findOrFail($data['leave_entitlement_group_id']);
            $this->assertGroupCompany($group);
        }

        $department->update([
            'leave_entitlement_group_id' => $data['leave_entitlement_group_id'] ?? null,
        ]);

        return $this->success($department->fresh());
    }

    public function balances(Request $request): JsonResponse
    {
        $companyId = $this->entitlements->companyIdFromContext();
        $year = (int) $request->integer('year', (int) now()->format('Y'));

        $rows = $this->entitlements->balancesForCompany(
            $companyId,
            $year,
            $request->integer('department_id') ?: null,
            $request->get('search'),
        );

        return $this->success([
            'year' => $year,
            'items' => $rows->values(),
        ]);
    }

    public function employeeBalance(Request $request, Employee $employee): JsonResponse
    {
        $this->assertEmployeeCompany($employee);
        $year = (int) $request->integer('year', (int) now()->format('Y'));

        $employee->load(['department:id,name,leave_entitlement_group_id', 'leaveEntitlementGroup:id,code,name,annual_days']);

        return $this->success($this->entitlements->balanceForEmployee($employee, $year));
    }

    private function assertGroupCompany(LeaveEntitlementGroup $group): void
    {
        if ($group->company_id !== CompanyContext::id()) {
            abort(404);
        }
    }

    private function assertEmployeeCompany(Employee $employee): void
    {
        if ($employee->company_id !== CompanyContext::id()) {
            abort(404);
        }
    }
}
