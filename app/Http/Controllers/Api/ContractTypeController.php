<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Models\ContractType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContractTypeController extends ApiController
{
    public function index(): JsonResponse
    {
        $types = ContractType::orderBy('code')->get();
        return $this->success($types);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:contract_types,code'],
            'name' => ['required', 'string', 'max:255'],
            'is_social_insurance' => ['sometimes', 'boolean'],
            'is_probation' => ['sometimes', 'boolean'],
            'default_duration_months' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $type = ContractType::create($data);

        return $this->success($type, 201);
    }

    public function show(ContractType $contractType): JsonResponse
    {
        return $this->success($contractType);
    }

    public function update(Request $request, ContractType $contractType): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('contract_types')->ignore($contractType->id)],
            'name' => ['required', 'string', 'max:255'],
            'is_social_insurance' => ['sometimes', 'boolean'],
            'is_probation' => ['sometimes', 'boolean'],
            'default_duration_months' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $contractType->update($data);

        return $this->success($contractType);
    }

    public function destroy(ContractType $contractType): JsonResponse
    {
        // Không cho phép xóa các loại hợp đồng cốt lõi nếu đang được sử dụng
        $contractType->delete();

        return $this->noContent();
    }
}
