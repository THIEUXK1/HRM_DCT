<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Models\EmployeeOnboardingTask;
use App\Support\EmployeeQueryScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['onboardingTasks.task', 'department', 'position', 'branch:id,name'])
            ->whereHas('onboardingTasks', fn ($q) => $q->where('status', '!=', 'completed'));

        EmployeeQueryScope::apply($query, $request);

        return $this->success($query->orderByDesc('hire_date')->limit(150)->get());
    }

    public function employeeTasks(Employee $employee): JsonResponse
    {
        return $this->success(
            $employee->onboardingTasks()->with('task')->orderBy('id')->get()
        );
    }

    public function updateTask(Request $request, Employee $employee, EmployeeOnboardingTask $employeeOnboardingTask): JsonResponse
    {
        abort_unless($employeeOnboardingTask->employee_id === $employee->id, 404);

        $data = $request->validate([
            'status' => 'nullable|in:pending,in_progress,completed',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if (array_key_exists('assigned_to', $data) && empty($data['assigned_to'])) {
            $data['assigned_to'] = null;
        }

        if (($data['status'] ?? null) === 'completed') {
            $data['completed_at'] = now();
        }

        $employeeOnboardingTask->update($data);

        return $this->success($employeeOnboardingTask->fresh('task'));
    }

    public function summary(Employee $employee): JsonResponse
    {
        $tasks = $employee->onboardingTasks()->with('task')->get();
        $total = $tasks->count();
        $done = $tasks->where('status', 'completed')->count();

        return $this->success([
            'employee_id' => $employee->id,
            'total' => $total,
            'completed' => $done,
            'percent' => $total > 0 ? round($done / $total * 100, 1) : 0,
            'onboarding_completed_at' => $employee->onboarding_completed_at,
            'tasks' => $tasks,
        ]);
    }

    public function complete(Employee $employee): JsonResponse
    {
        $pending = $employee->onboardingTasks()->where('status', '!=', 'completed')->count();

        if ($pending > 0) {
            return $this->error("Còn {$pending} mục chưa hoàn thành.", 422);
        }

        $employee->update(['onboarding_completed_at' => now()]);

        return $this->success($employee->fresh(['onboardingTasks.task']));
    }
}
