<?php

namespace App\Http\Controllers\Api;

use App\Services\Hr\OrgStructureImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrgStructureController extends ApiController
{
    public function __construct(private OrgStructureImportService $importService) {}

    /**
     * POST /org-structure/import
     *
     * Request: multipart/form-data
     *   file: xlsx|csv file
     *   type: branches|departments|positions
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
            'type' => ['required', 'in:branches,departments,positions'],
        ]);

        $result = $this->importService->import(
            $request->input('type'),
            $request->file('file')
        );

        return $this->success($result);
    }
}
