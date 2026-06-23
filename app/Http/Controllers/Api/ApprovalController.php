<?php

namespace App\Http\Controllers\Api;

use App\Models\ApprovalInstance;
use App\Services\AuditLogger;
use App\Services\Approval\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends ApiController
{
    public function inbox(ApprovalService $service): JsonResponse
    {
        return $this->success($service->pendingInbox());
    }

    public function approve(Request $request, ApprovalInstance $approvalInstance, ApprovalService $service): JsonResponse
    {
        $instance = $service->approve(
            $approvalInstance,
            (int) auth()->id(),
            $request->input('comment')
        );

        AuditLogger::approved($approvalInstance,
            "Duyệt {$approvalInstance->approvable_type} #{$approvalInstance->approvable_id}"
            .($request->filled('comment') ? ' — '.$request->input('comment') : '')
        );

        return $this->success($instance);
    }

    public function reject(Request $request, ApprovalInstance $approvalInstance, ApprovalService $service): JsonResponse
    {
        $instance = $service->reject(
            $approvalInstance,
            (int) auth()->id(),
            $request->input('comment')
        );

        AuditLogger::rejected($approvalInstance,
            "Từ chối {$approvalInstance->approvable_type} #{$approvalInstance->approvable_id}"
            .($request->filled('comment') ? ' — '.$request->input('comment') : '')
        );

        return $this->success($instance);
    }
}
