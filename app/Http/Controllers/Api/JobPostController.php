<?php

namespace App\Http\Controllers\Api;

use App\Models\JobPost;
use App\Models\RecruitmentRequest;
use App\Services\Recruitment\RecruitmentRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobPostController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = JobPost::with(['recruitmentRequest.department', 'recruitmentRequest.position'])
            ->orderByDesc('created_at');

        if ($request->filled('recruitment_request_id')) {
            $query->where('recruitment_request_id', $request->integer('recruitment_request_id'));
        }

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('channel', 'like', "%{$search}%");
            });
        }

        return $this->success($query->limit(100)->get());
    }

    public function store(Request $request, RecruitmentRequestService $requests): JsonResponse
    {
        $data = $request->validate([
            'recruitment_request_id' => 'required|exists:recruitment_requests,id',
            'title' => 'required|string|max:255',
            'job_description' => 'nullable|string',
            'channel' => 'nullable|string|max:100',
            'external_url' => 'nullable|url|max:500',
        ]);

        $recruitmentRequest = RecruitmentRequest::findOrFail($data['recruitment_request_id']);
        $requests->assertCanPublishJob($recruitmentRequest);

        $post = JobPost::create([
            ...$data,
            'status' => 'draft',
        ]);

        return $this->success($post->load('recruitmentRequest'), 201);
    }

    public function show(JobPost $jobPost): JsonResponse
    {
        return $this->success($jobPost->load(['recruitmentRequest', 'candidates']));
    }

    public function update(Request $request, JobPost $jobPost): JsonResponse
    {
        $jobPost->update($request->validate([
            'title' => 'sometimes|string|max:255',
            'job_description' => 'nullable|string',
            'channel' => 'nullable|string|max:100',
            'external_url' => 'nullable|url|max:500',
            'status' => 'nullable|in:draft,published,closed',
        ]));

        return $this->success($jobPost->fresh('recruitmentRequest'));
    }

    public function publish(JobPost $jobPost): JsonResponse
    {
        $jobPost->update([
            'status' => 'published',
            'published_at' => now()->toDateString(),
        ]);

        return $this->success($jobPost);
    }
}
