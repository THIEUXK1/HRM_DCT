<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\EmployeeDocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\Hr\HrFileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentController extends ApiController
{
    public function __construct(protected HrFileStorage $storage) {}

    public function index(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return $this->success($employee->documents()->orderByDesc('created_at')->get());
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);

        $meta = $request->validate([
            'type' => ['required', Rule::in(array_keys(config('hr_vn.document_types')))],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issuing_authority' => ['nullable', 'string', 'max:255'],
            'issued_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'file' => ['required', 'file', 'max:15360', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        $stored = $this->storage->storeEmployeeDocument($request->file('file'), $employee->id);

        $doc = $employee->documents()->create([
            ...$meta,
            ...$stored,
            'uploaded_by' => auth()->id(),
        ]);

        return $this->success($doc, 201);
    }

    /** Ghi nhận metadata khi chưa có file (legacy). */
    public function storeMetadata(EmployeeDocumentRequest $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);

        $doc = $employee->documents()->create($request->validated());

        return $this->success($doc, 201);
    }

    public function download(Employee $employee, EmployeeDocument $document): StreamedResponse
    {
        $this->authorize('view', $employee);
        abort_unless($document->employee_id === $employee->id, 404);
        abort_unless($document->file_path, 404, 'Tài liệu chưa có file đính kèm.');

        return $this->storage->downloadResponse(
            $document->file_path,
            $document->file_name,
            $document->file_disk
        );
    }

    public function destroy(Employee $employee, EmployeeDocument $document): JsonResponse
    {
        $this->authorize('update', $employee);
        abort_unless($document->employee_id === $employee->id, 404);

        $this->storage->delete($document->file_path, $document->file_disk);
        $document->delete();

        return $this->noContent();
    }
}
