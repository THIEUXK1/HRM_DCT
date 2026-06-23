<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

class RecruitmentMetaController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->success([
            'request_statuses' => config('recruitment.request_statuses'),
            'job_post_statuses' => config('recruitment.job_post_statuses'),
            'candidate_stages' => config('recruitment.candidate_stages'),
            'offer_statuses' => config('recruitment.offer_statuses'),
            'interview_statuses' => config('recruitment.interview_statuses'),
            'document_types' => config('recruitment.document_types'),
            'scorecard_criteria' => config('recruitment.scorecard_criteria'),
            'contract_types' => config('recruitment.contract_types'),
        ]);
    }
}
