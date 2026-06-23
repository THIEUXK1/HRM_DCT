<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanySettingController extends ApiController
{
    public function index(): JsonResponse
    {
        $settings = CompanySetting::pluck('value', 'key')->toArray();
        return $this->success($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['required', 'string'],
        ]);

        $companyId = \App\Support\CompanyContext::id();

        foreach ($data['settings'] as $key => $value) {
            CompanySetting::updateOrCreate(
                ['company_id' => $companyId, 'key' => $key],
                ['value' => $value]
            );
        }

        $settings = CompanySetting::pluck('value', 'key')->toArray();
        return $this->success($settings);
    }
}
