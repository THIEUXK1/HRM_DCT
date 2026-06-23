<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Models\EmployeeTransfer;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeTransferController extends ApiController
{
    public function index(Employee $employee): JsonResponse
    {
        return $this->success(
            $employee->transfers()
                ->with(['fromBranch', 'toBranch', 'fromDepartment', 'toDepartment', 'fromPosition', 'toPosition'])
                ->orderByDesc('effective_date')
                ->get()
        );
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'decision_number' => 'required|string',
            'effective_date' => 'required|date',
            'type' => 'required|in:promotion,transfer,demotion',
            'reason' => 'nullable|string',
            'signed_by' => 'nullable|string',
            'to_branch_id' => 'nullable|exists:branches,id',
            'to_department_id' => 'nullable|exists:departments,id',
            'to_position_id' => 'nullable|exists:positions,id',
        ]);

        $transfer = $employee->transfers()->create($data + [
            'company_id' => CompanyContext::id() ?? $employee->company_id,
            'from_branch_id' => $employee->branch_id,
            'from_department_id' => $employee->department_id,
            'from_position_id' => $employee->position_id,
            'status' => 'pending',
        ]);

        return $this->success($transfer, 201);
    }

    public function approve(Employee $employee, EmployeeTransfer $transfer): JsonResponse
    {
        if ($transfer->status !== 'pending') {
            return $this->error('Quyết định này đã được duyệt hoặc từ chối.', 400);
        }

        $transfer->update(['status' => 'approved']);

        // Update the employee profile directly!
        $updateData = [];
        if ($transfer->to_branch_id) {
            $updateData['branch_id'] = $transfer->to_branch_id;
        }
        if ($transfer->to_department_id) {
            $updateData['department_id'] = $transfer->to_department_id;
        }
        if ($transfer->to_position_id) {
            $updateData['position_id'] = $transfer->to_position_id;
        }

        if (!empty($updateData)) {
            $employee->update($updateData);
        }

        return $this->success($transfer->load(['fromBranch', 'toBranch', 'fromDepartment', 'toDepartment', 'fromPosition', 'toPosition']));
    }
}
