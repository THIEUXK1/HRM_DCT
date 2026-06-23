<?php

namespace App\Http\Controllers\Api;

use App\Models\Candidate;
use App\Models\Company;
use App\Models\JobPost;
use App\Services\Hr\HrFileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicRecruitmentController extends ApiController
{
    public function __construct(protected HrFileStorage $storage) {}

    public function jobPosts(Request $request): JsonResponse
    {
        $query = JobPost::with(['recruitmentRequest.company', 'recruitmentRequest.department', 'recruitmentRequest.position'])
            ->where('status', 'published')
            ->orderByDesc('published_at');

        if ($request->filled('company_id')) {
            $query->whereHas('recruitmentRequest', fn ($q) => $q->where('company_id', $request->integer('company_id')));
        }

        return $this->success($query->paginate($request->integer('per_page', 20)));
    }

    public function show(JobPost $jobPost): JsonResponse
    {
        abort_unless($jobPost->status === 'published', 404);

        return $this->success($jobPost->load(['recruitmentRequest.company', 'recruitmentRequest.department', 'recruitmentRequest.position']));
    }

    public function apply(Request $request, JobPost $jobPost): JsonResponse
    {
        abort_unless($jobPost->status === 'published', 404);

        $company = Company::findOrFail($jobPost->recruitmentRequest->company_id);

        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'experience_summary' => 'nullable|string',
            'skills' => 'nullable|array',
            'source' => 'nullable|string|max:100',
            'file' => 'nullable|file|max:15360|mimes:pdf,doc,docx',
        ]);

        $candidate = Candidate::create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'job_post_id' => $jobPost->id,
            'full_name' => $data['full_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'experience_summary' => $data['experience_summary'] ?? null,
            'skills' => $data['skills'] ?? null,
            'source' => $data['source'] ?? 'career_portal',
            'stage' => 'applied',
        ]);

        if ($request->hasFile('file')) {
            $stored = $this->storage->storeCandidateDocument($request->file('file'), $candidate->id);
            $candidate->documents()->create([
                'type' => 'cv',
                ...$stored,
            ]);
        }

        return $this->success([
            'message' => 'Đã nhận hồ sơ. HR sẽ liên hệ bạn sớm.',
            'candidate_id' => $candidate->id,
        ], 201);
    }
}
