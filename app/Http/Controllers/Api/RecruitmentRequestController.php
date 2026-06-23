<?php

namespace App\Http\Controllers\Api;

use App\Models\ApprovalInstance;
use App\Models\Company;
use App\Models\RecruitmentRequest;
use App\Services\Recruitment\RecruitmentRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RecruitmentRequestController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = RecruitmentRequest::with(['department', 'position', 'jobPosts'])
            ->orderByDesc('created_at');

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $this->success($query->limit(200)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'department_id' => 'nullable|exists:departments,id',
            'position_id' => 'nullable|exists:positions,id',
            'title' => 'required|string|max:255',
            'headcount' => 'integer|min:1',
            'description' => 'nullable|string',
        ]);

        $company = Company::findOrFail($data['company_id']);
        $data['tenant_id'] = $company->tenant_id;
        $data['code'] = 'REQ-'.Str::upper(Str::random(8));
        $data['requested_by'] = auth()->id();
        $data['status'] = 'draft';

        $record = RecruitmentRequest::create($data);

        return $this->success($record->load(['department', 'position']), 201);
    }

    public function show(RecruitmentRequest $recruitmentRequest): JsonResponse
    {
        $approval = ApprovalInstance::where('entity_type', 'recruitment_request')
            ->where('entity_id', $recruitmentRequest->id)
            ->latest()
            ->first();

        return $this->success([
            'request' => $recruitmentRequest->load(['jobPosts', 'department', 'position']),
            'approval' => $approval,
        ]);
    }

    public function update(Request $request, RecruitmentRequest $recruitmentRequest): JsonResponse
    {
        abort_unless(in_array($recruitmentRequest->status, ['draft', 'rejected'], true), 422, 'Không thể sửa yêu cầu đã gửi duyệt.');

        $recruitmentRequest->update($request->validate([
            'department_id' => 'nullable|exists:departments,id',
            'position_id' => 'nullable|exists:positions,id',
            'title' => 'sometimes|string|max:255',
            'headcount' => 'integer|min:1',
            'description' => 'nullable|string',
        ]));

        return $this->success($recruitmentRequest->fresh(['department', 'position']));
    }

    public function submit(RecruitmentRequest $recruitmentRequest, RecruitmentRequestService $service): JsonResponse
    {
        return $this->success($service->submit($recruitmentRequest));
    }
}
