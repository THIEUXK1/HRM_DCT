<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\EmployeeWorkSchedule;
use Illuminate\Support\Facades\DB;

class WorkScheduleBulkAssignService
{
    /**
     * Gán ca hàng loạt cho nhiều phòng ban và nhân viên.
     *
     * @return array{assigned: int, skipped: int, errors: list<string>}
     */
    public function assignBulk(
        int $companyId,
        array $departmentIds,
        array $employeeIds,
        int $groupId,
        int $patternId,
        string $effectiveFrom,
        ?string $effectiveTo = null,
        bool $weekendSwap = false,
    ): array {
        // 1. Lấy danh sách ID nhân viên thuộc các phòng ban được chọn
        $deptEmployeeIds = [];
        if (!empty($departmentIds)) {
            $deptEmployeeIds = Employee::where('company_id', $companyId)
                ->whereIn('department_id', $departmentIds)
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();
        }

        // 2. Lấy danh sách ID nhân viên được chọn riêng
        $explicitEmployeeIds = [];
        if (!empty($employeeIds)) {
            $explicitEmployeeIds = Employee::where('company_id', $companyId)
                ->whereIn('id', $employeeIds)
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();
        }

        // 3. Gộp và loại bỏ trùng lặp
        $targetEmployeeIds = array_values(array_unique(array_merge($deptEmployeeIds, $explicitEmployeeIds)));

        if (empty($targetEmployeeIds)) {
            return [
                'assigned' => 0,
                'skipped' => 0,
                'errors' => ['Không tìm thấy nhân viên hoạt động nào trong danh sách đã chọn.']
            ];
        }

        $employees = Employee::whereIn('id', $targetEmployeeIds)->get();

        $assigned = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use (
            $employees, $companyId, $groupId, $patternId, $effectiveFrom, $effectiveTo, $weekendSwap,
            &$assigned, &$skipped, &$errors
        ) {
            foreach ($employees as $employee) {
                // Kiểm tra xem nhân viên đã có lịch làm việc trùng lặp trong khoảng thời gian này chưa
                $overlap = EmployeeWorkSchedule::where('employee_id', $employee->id)
                    ->where(function ($q) use ($effectiveFrom, $effectiveTo) {
                        $q->where(function ($q1) use ($effectiveFrom) {
                            $q1->whereNull('effective_to')
                               ->orWhere('effective_to', '>=', $effectiveFrom);
                        });

                        if ($effectiveTo !== null) {
                            $q->where('effective_from', '<=', $effectiveTo);
                        }
                    })
                    ->exists();

                if ($overlap) {
                    $skipped++;
                    $errors[] = "{$employee->employee_code}: đã có lịch làm việc trong khoảng thời gian này.";
                    continue;
                }

                EmployeeWorkSchedule::create([
                    'company_id' => $companyId,
                    'employee_id' => $employee->id,
                    'work_schedule_group_id' => $groupId,
                    'work_schedule_pattern_id' => $patternId,
                    'effective_from' => $effectiveFrom,
                    'effective_to' => $effectiveTo,
                    'weekend_swap_enabled' => $weekendSwap,
                ]);
                $assigned++;
            }
        });

        return compact('assigned', 'skipped', 'errors');
    }

    /**
     * Gán ca hàng loạt theo phòng ban (backward compatibility).
     *
     * @return array{assigned: int, skipped: int, errors: list<string>}
     */
    public function assignByDepartment(
        int $companyId,
        int $departmentId,
        int $groupId,
        int $patternId,
        string $effectiveFrom,
        ?string $effectiveTo = null,
        bool $weekendSwap = false,
    ): array {
        return $this->assignBulk(
            $companyId,
            [$departmentId],
            [],
            $groupId,
            $patternId,
            $effectiveFrom,
            $effectiveTo,
            $weekendSwap
        );
    }
}
