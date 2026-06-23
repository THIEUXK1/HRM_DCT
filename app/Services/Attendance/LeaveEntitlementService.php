<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\LeaveEntitlementGroup;
use App\Models\LeaveRequest;
use App\Services\Company\CompanyPolicyResolver;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Quỹ phép năm: ưu tiên ghi đè cá nhân → nhóm NV → nhóm phòng ban → chính sách công ty.
 */
class LeaveEntitlementService
{
    /** @var list<string> */
    private const ANNUAL_LEAVE_CODES = ['PHEP', 'PN'];

    public function ensureDefaultGroups(int $companyId): void
    {
        if (LeaveEntitlementGroup::where('company_id', $companyId)->exists()) {
            return;
        }

        LeaveEntitlementGroup::insert([
            [
                'company_id' => $companyId,
                'code' => 'STANDARD',
                'name' => 'Tiêu chuẩn (BLLĐ Điều 113)',
                'annual_days' => 12,
                'description' => 'Phép năm tiêu chuẩn 12 ngày/năm.',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $companyId,
                'code' => 'HEAVY_LABOR',
                'name' => 'Lao động nặng nhọc / độc hại / đặc thù',
                'annual_days' => 14,
                'description' => 'Theo BLLĐ 2019 — lao động nặng nhọc, độc hại, nguy hiểm hoặc làm việc ở nơi có điều kiện lao động đặc biệt.',
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function resolveAnnualDays(Employee $employee): float
    {
        if ($employee->annual_leave_days_override !== null) {
            return (float) $employee->annual_leave_days_override;
        }

        $group = $this->resolveGroup($employee);
        if ($group) {
            return (float) $group->annual_days;
        }

        $policyDays = CompanyPolicyResolver::for($employee->company_id, null, $employee->id)
            ->getString('annual_leave_standard');

        if ($policyDays !== null && $policyDays !== '') {
            return (float) $policyDays;
        }

        return (float) config('company_policy_defaults.annual_leave_standard', 12);
    }

    public function resolveGroup(Employee $employee): ?LeaveEntitlementGroup
    {
        if ($employee->relationLoaded('leaveEntitlementGroup') && $employee->leave_entitlement_group_id) {
            return $employee->leaveEntitlementGroup;
        }

        if ($employee->leave_entitlement_group_id) {
            return LeaveEntitlementGroup::find($employee->leave_entitlement_group_id);
        }

        $employee->loadMissing('department:id,leave_entitlement_group_id');

        if ($employee->department?->leave_entitlement_group_id) {
            return LeaveEntitlementGroup::find($employee->department->leave_entitlement_group_id);
        }

        return LeaveEntitlementGroup::query()
            ->where('company_id', $employee->company_id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    public function balanceForEmployee(Employee $employee, ?int $year = null): array
    {
        $year = $year ?? (int) now()->format('Y');
        $total = $this->resolveAnnualDays($employee);
        $used = $this->usedAnnualLeaveDays($employee, $year);
        $group = $this->resolveGroup($employee);

        $source = 'company_policy';
        if ($employee->annual_leave_days_override !== null) {
            $source = 'employee_override';
        } elseif ($employee->leave_entitlement_group_id) {
            $source = 'employee_group';
        } elseif ($employee->department?->leave_entitlement_group_id) {
            $source = 'department_group';
        } elseif ($group?->is_default) {
            $source = 'default_group';
        } elseif ($group) {
            $source = 'group';
        }

        return [
            'employee_id' => $employee->id,
            'year' => $year,
            'annual_days' => $total,
            'used_days' => round($used, 2),
            'remaining_days' => round(max(0, $total - $used), 2),
            'source' => $source,
            'group' => $group ? [
                'id' => $group->id,
                'code' => $group->code,
                'name' => $group->name,
            ] : null,
            'annual_leave_days_override' => $employee->annual_leave_days_override !== null
                ? (float) $employee->annual_leave_days_override
                : null,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function balancesForCompany(int $companyId, int $year, ?int $departmentId = null, ?string $search = null): Collection
    {
        $query = Employee::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->with([
                'department:id,name,leave_entitlement_group_id',
                'leaveEntitlementGroup:id,code,name,annual_days',
            ]);

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        if ($search) {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('full_name', 'like', $term)
                    ->orWhere('employee_code', 'like', $term);
            });
        }

        return $query->orderBy('full_name')->get()->map(
            fn (Employee $employee) => array_merge(
                $this->balanceForEmployee($employee, $year),
                [
                    'full_name' => $employee->full_name,
                    'employee_code' => $employee->employee_code,
                    'department' => $employee->department?->name,
                ]
            )
        );
    }

    public function usedAnnualLeaveDays(Employee $employee, int $year): float
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();

        return (float) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('end_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('start_date', '<=', $start->toDateString())
                            ->where('end_date', '>=', $end->toDateString());
                    });
            })
            ->whereHas('leaveType', function ($q) use ($employee) {
                $q->where('company_id', $employee->company_id)
                    ->whereIn('code', self::ANNUAL_LEAVE_CODES);
            })
            ->sum('total_days');
    }

    public function companyIdFromContext(): int
    {
        return CompanyContext::id() ?? throw new \RuntimeException('Company context required.');
    }
}
