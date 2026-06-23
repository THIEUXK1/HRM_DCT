<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Gom danh sách nhân viên từ employee_id | employee_ids[] | department_id.
 */
class EmployeeScopeResolver
{
    /**
     * @return Collection<int, Employee>
     */
    public static function resolve(
        int $companyId,
        ?int $employeeId = null,
        ?array $employeeIds = null,
        ?int $departmentId = null,
    ): Collection {
        $ids = self::collectIds($employeeId, $employeeIds, $departmentId);

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'employee_ids' => ['Chọn ít nhất một nhân viên hoặc một phòng ban.'],
            ]);
        }

        $employees = Employee::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->orderBy('employee_code')
            ->get();

        $missing = $ids->diff($employees->pluck('id'));
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'employee_ids' => ['Một số nhân viên không thuộc công ty hoặc không còn hoạt động.'],
            ]);
        }

        return $employees;
    }

    /** @return Collection<int, int> */
    private static function collectIds(?int $employeeId, ?array $employeeIds, ?int $departmentId): Collection
    {
        if ($departmentId) {
            return Employee::query()
                ->where('department_id', $departmentId)
                ->where('is_active', true)
                ->pluck('id');
        }

        if (is_array($employeeIds) && $employeeIds !== []) {
            return collect($employeeIds)
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();
        }

        if ($employeeId) {
            return collect([(int) $employeeId]);
        }

        return collect();
    }

    /** @return array<string, array<int, string>> */
    public static function bulkTargetRules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id', 'required_without_all:employee_ids,department_id'],
            'employee_ids' => ['nullable', 'array', 'min:1', 'required_without_all:employee_id,department_id'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id', 'required_without_all:employee_id,employee_ids'],
        ];
    }
}
