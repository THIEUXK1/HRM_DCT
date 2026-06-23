<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\EmployeeDependentRequest;
use App\Models\Employee;
use App\Models\EmployeeDependent;

class EmployeeDependentController extends ApiController
{
    public function index(Employee $employee): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $employee);

        return $this->success($employee->dependents()->orderBy('full_name')->get());
    }

    public function store(EmployeeDependentRequest $request, Employee $employee): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $employee);

        $dependent = $employee->dependents()->create($request->validated());
        $employee->update(['pit_dependents_count' => $employee->activeDependentsCount()]);

        return $this->success($dependent, 201);
    }

    public function update(EmployeeDependentRequest $request, Employee $employee, EmployeeDependent $dependent): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $employee);
        abort_unless($dependent->employee_id === $employee->id, 404);

        $dependent->update($request->validated());
        $employee->update(['pit_dependents_count' => $employee->activeDependentsCount()]);

        return $this->success($dependent);
    }

    public function destroy(Employee $employee, EmployeeDependent $dependent): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $employee);
        abort_unless($dependent->employee_id === $employee->id, 404);

        $dependent->delete();
        $employee->update(['pit_dependents_count' => $employee->activeDependentsCount()]);

        return $this->noContent();
    }
}
