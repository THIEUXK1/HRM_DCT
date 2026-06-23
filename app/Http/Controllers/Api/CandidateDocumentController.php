<?php

namespace App\Http\Controllers\Api;

use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Services\Hr\HrFileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CandidateDocumentController extends ApiController
{
    public function __construct(protected HrFileStorage $storage) {}

    public function index(Candidate $candidate): JsonResponse
    {
        return $this->success($candidate->documents()->orderByDesc('created_at')->get());
    }

    public function store(Request $request, Candidate $candidate): JsonResponse
    {
        $meta = $request->validate([
            'type' => ['required', Rule::in(array_keys(config('recruitment.document_types')))],
            'file' => ['required', 'file', 'max:15360', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $stored = $this->storage->storeCandidateDocument($request->file('file'), $candidate->id);

        $doc = $candidate->documents()->create([
            'type' => $meta['type'],
            ...$stored,
            'uploaded_by' => auth()->id(),
        ]);

        return $this->success($doc, 201);
    }

    public function download(Candidate $candidate, CandidateDocument $document): StreamedResponse
    {
        abort_unless($document->candidate_id === $candidate->id, 404);
        abort_unless($document->file_path, 404);

        return $this->storage->downloadResponse(
            $document->file_path,
            $document->file_name,
            $document->file_disk
        );
    }

    public function destroy(Candidate $candidate, CandidateDocument $document): JsonResponse
    {
        abort_unless($document->candidate_id === $candidate->id, 404);

        $this->storage->delete($document->file_path, $document->file_disk);
        $document->delete();

        return $this->success(['deleted' => true]);
    }
}
