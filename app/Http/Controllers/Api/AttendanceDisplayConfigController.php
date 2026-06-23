<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\UpdateAttendanceDisplayConfigRequest;
use App\Services\Attendance\AttendanceDisplayConfigService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;

class AttendanceDisplayConfigController extends ApiController
{
    public function __construct(
        private readonly AttendanceDisplayConfigService $displayConfig,
    ) {}

    public function show(): JsonResponse
    {
        return $this->success($this->displayConfig->forCompany(CompanyContext::id()));
    }

    public function update(UpdateAttendanceDisplayConfigRequest $request): JsonResponse
    {
        $companyId = CompanyContext::id();

        return $this->success(
            $this->displayConfig->save($companyId, $request->validated('config'))
        );
    }
}
