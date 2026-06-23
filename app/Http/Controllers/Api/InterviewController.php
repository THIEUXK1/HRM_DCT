<?php

namespace App\Http\Controllers\Api;

use App\Models\Candidate;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterviewController extends ApiController
{
    public function store(Request $request, Candidate $candidate): JsonResponse
    {
        $data = $request->validate([
            'round' => 'integer|min:1|max:10',
            'scheduled_at' => 'required|date',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|in:scheduled,completed,cancelled',
        ]);

        $interview = $candidate->interviews()->create([
            ...$data,
            'round' => $data['round'] ?? ($candidate->interviews()->max('round') + 1),
            'status' => $data['status'] ?? 'scheduled',
        ]);

        if ($candidate->stage === 'screening' || $candidate->stage === 'applied') {
            $candidate->update(['stage' => 'interview']);
        }

        return $this->success($interview, 201);
    }

    public function storeFeedback(Request $request, Interview $interview): JsonResponse
    {
        $data = $request->validate([
            'score' => 'nullable|integer|min:0|max:100',
            'feedback' => 'nullable|string',
            'recommendation' => 'nullable|in:hire,no_hire,maybe',
            'scorecard' => 'nullable|array',
            'scorecard.*.criterion' => 'required_with:scorecard|string',
            'scorecard.*.score' => 'required_with:scorecard|integer|min:1|max:5',
        ]);

        $feedback = InterviewFeedback::updateOrCreate(
            [
                'interview_id' => $interview->id,
                'interviewer_id' => auth()->id(),
            ],
            [
                ...$data,
                'scorecard' => $data['scorecard'] ?? null,
            ]
        );

        $interview->update(['status' => 'completed']);

        return $this->success($feedback);
    }
}
