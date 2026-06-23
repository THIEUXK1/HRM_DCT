<?php

namespace App\Http\Controllers\Api;

use App\Models\ContractType;

class HrMetaController extends ApiController
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $meta = config('hr_vn');
        
        $dbContractTypes = ContractType::where('is_active', true)->pluck('name', 'code')->toArray();
        if (!empty($dbContractTypes)) {
            $meta['contract_types'] = $dbContractTypes;
        }
        
        return $this->success($meta);
    }
}
