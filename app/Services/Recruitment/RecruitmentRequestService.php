<?php

namespace App\Services\Recruitment;

use App\Models\RecruitmentRequest;
use App\Services\Approval\ApprovalService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RecruitmentRequestService
{
    public function __construct(protected ApprovalService $approvals) {}

    public function submit(RecruitmentRequest $request): RecruitmentRequest
    {
        if (! in_array($request->status, ['draft', 'rejected'], true)) {
            throw new RuntimeException('Chỉ yêu cầu nháp hoặc bị từ chối mới gửi duyệt lại.');
        }

        return DB::transaction(function () use ($request) {
            $request->update([
                'status' => 'pending_approval',
                'submitted_at' => now(),
            ]);

            $this->approvals->start('recruitment_request', $request->id, $request->tenant_id);

            return $request->fresh(['department', 'position', 'jobPosts']);
        });
    }

    public function assertCanPublishJob(RecruitmentRequest $request): void
    {
        if ($request->status !== 'approved') {
            throw new RuntimeException('Yêu cầu tuyển dụng chưa được duyệt headcount.');
        }
    }
}
