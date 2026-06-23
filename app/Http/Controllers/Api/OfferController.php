<?php

namespace App\Http\Controllers\Api;

use App\Models\Candidate;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends ApiController
{
    public function store(Request $request, Candidate $candidate): JsonResponse
    {
        $data = $request->validate([
            'salary_base' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'contract_type' => 'nullable|string|max:50',
            'letter_notes' => 'nullable|string',
        ]);

        $offer = $candidate->offers()->create([
            ...$data,
            'contract_type' => $data['contract_type'] ?? 'probation',
            'status' => 'pending',
        ]);

        $candidate->update(['stage' => 'offer']);

        return $this->success($offer, 201);
    }

    public function accept(Offer $offer): JsonResponse
    {
        abort_unless($offer->status === 'pending', 422, 'Offer không ở trạng thái chờ phản hồi.');

        $offer->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return $this->success($offer->load('candidate'));
    }

    public function decline(Offer $offer): JsonResponse
    {
        $offer->update(['status' => 'declined']);

        return $this->success($offer);
    }

    public function letter(Offer $offer): JsonResponse
    {
        $offer->load(['candidate.company', 'candidate.jobPost']);

        return $this->success([
            'html' => view('recruitment.offer-letter', ['offer' => $offer])->render(),
        ]);
    }
}
