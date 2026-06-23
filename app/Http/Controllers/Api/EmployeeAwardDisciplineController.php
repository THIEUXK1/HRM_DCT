<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Models\EmployeeAwardDiscipline;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeAwardDisciplineController extends ApiController
{
    public function index(Employee $employee): JsonResponse
    {
        return $this->success($employee->awards()->orderByDesc('decision_date')->get());
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:award,discipline',
            'decision_number' => 'required|string',
            'decision_date' => 'required|date',
            'reason' => 'required|string',
            'amount' => 'nullable|numeric|min:0',
            'signed_by' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $award = $employee->awards()->create($data + [
            'company_id' => CompanyContext::id() ?? $employee->company_id
        ]);

        return $this->success($award, 201);
    }

    public function destroy(Employee $employee, EmployeeAwardDiscipline $awardsDiscipline): JsonResponse
    {
        $awardsDiscipline->delete();
        return $this->noContent();
    }
}
