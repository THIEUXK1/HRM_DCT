<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $companyId = app()->bound('current_company_id') ? app('current_company_id') : null;

        $query = AuditLog::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($request->filled('category'),    fn ($q) => $q->where('action_category', $request->category))
            ->when($request->filled('action'),      fn ($q) => $q->where('action', $request->action))
            ->when($request->filled('entity_type'), fn ($q) => $q->where('entity_type', 'like', '%'.$request->entity_type.'%'))
            ->when($request->filled('entity_id'),   fn ($q) => $q->where('entity_id', $request->integer('entity_id')))
            ->when($request->filled('actor_id'),    fn ($q) => $q->where('actor_id', $request->integer('actor_id')))
            ->when($request->filled('from'),        fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'),          fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->where('description', 'like', '%'.$request->q.'%')
                        ->orWhere('actor_name', 'like', '%'.$request->q.'%');
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 50));

        return $this->success($query);
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        $this->authorize('view', $auditLog);

        return $this->success($auditLog);
    }
}
