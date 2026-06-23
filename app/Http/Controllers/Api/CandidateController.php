<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\CandidateRequest;
use App\Models\Candidate;
use App\Support\QuerySearch;
use App\Services\Recruitment\HireCandidateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Candidate::with(['jobPost', 'employee'])
            ->orderByDesc('created_at');

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('stage')) {
            $query->where('stage', $request->string('stage'));
        }

        if ($request->boolean('talent_pool')) {
            $query->where('stage', 'talent_pool');
        }

        QuerySearch::candidate($query, $request->get('search'));

        return $this->success($query->orderByDesc('created_at')->paginate(request()->integer('per_page', 50)));
    }

    public function store(CandidateRequest $request): JsonResponse
    {
        $candidate = Candidate::create($request->validated());

        return $this->success($candidate, 201);
    }

    public function show(Candidate $candidate): JsonResponse
    {
        return $this->success($candidate->load([
            'interviews.feedbacks',
            'offers',
            'employee',
            'documents',
            'jobPost.recruitmentRequest',
        ]));
    }

    public function update(CandidateRequest $request, Candidate $candidate): JsonResponse
    {
        $candidate->update($request->validated());

        return $this->success($candidate);
    }

    public function updateStage(Request $request, Candidate $candidate): JsonResponse
    {
        $data = $request->validate([
            'stage' => 'required|in:'.implode(',', array_keys(config('recruitment.candidate_stages'))),
        ]);

        $updates = ['stage' => $data['stage']];

        if ($data['stage'] === 'rejected') {
            $updates['rejected_at'] = now();
        }

        if ($data['stage'] !== 'rejected') {
            $updates['rejected_at'] = null;
        }

        $candidate->update($updates);

        return $this->success($candidate);
    }

    public function reject(Candidate $candidate): JsonResponse
    {
        $candidate->update([
            'stage' => 'rejected',
            'rejected_at' => now(),
        ]);

        return $this->success($candidate);
    }

    public function moveToTalentPool(Candidate $candidate): JsonResponse
    {
        abort_if($candidate->employee_id, 422, 'Ứng viên đã là nhân viên.');

        $candidate->update([
            'stage' => 'talent_pool',
            'rejected_at' => null,
        ]);

        return $this->success($candidate);
    }

    public function hire(Request $request, Candidate $candidate, HireCandidateService $service): JsonResponse
    {
        try {
            $employee = $service->hire($candidate, $request->validate([
                'branch_id' => 'nullable|exists:branches,id',
                'department_id' => 'nullable|exists:departments,id',
                'position_id' => 'nullable|exists:positions,id',
                'employee_code' => 'nullable|string|unique:employees,employee_code',
                'manager_id' => 'nullable|exists:employees,id',
                'onboarding_buddy_user_id' => 'nullable|exists:users,id',
            ]));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($employee, 201);
    }
}
