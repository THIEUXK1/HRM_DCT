<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ApplyPolicyTemplateRequest;
use App\Models\Company;
use App\Services\Company\CompanyPolicyTemplateService;
use Illuminate\Http\JsonResponse;

class PolicyTemplateController extends ApiController
{
    public function __construct(
        private readonly CompanyPolicyTemplateService $templateService,
    ) {}

    public function index(): JsonResponse
    {
        $user = auth()->user();
        if (! $user || (! $user->hasRole('admin') && ! $user->can('policy_templates.view'))) {
            abort(403);
        }

        return $this->success($this->templateService->listTemplates());
    }

    public function show(string $code): JsonResponse
    {
        $user = auth()->user();
        if (! $user || (! $user->hasRole('admin') && ! $user->can('policy_templates.view'))) {
            abort(403);
        }

        $template = $this->templateService->findTemplate($code);
        if (! $template) {
            return $this->error('Không tìm thấy gói chính sách.', 404);
        }

        return $this->success([
            'template' => $template,
            'definition' => config("company_policy_templates.{$code}", []),
        ]);
    }

    public function apply(ApplyPolicyTemplateRequest $request, Company $company): JsonResponse
    {
        $this->authorize('applyTemplate', $company);

        $data = $request->validated();
        $result = $this->templateService->apply(
            $company->id,
            $data['template_code'],
            (bool) ($data['overwrite'] ?? false),
        );

        return $this->success($result);
    }
}
