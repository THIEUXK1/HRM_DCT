<?php

namespace App\Services\Approval;

use App\Models\ApprovalAction;
use App\Models\ApprovalInstance;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\RecruitmentRequest;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApprovalService
{
    public function start(string $entityType, int $entityId, int $tenantId): ?ApprovalInstance
    {
        $workflow = ApprovalWorkflow::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->first();

        if (! $workflow) {
            return null;
        }

        return ApprovalInstance::create([
            'approval_workflow_id' => $workflow->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'current_step' => 1,
            'status' => 'pending',
        ]);
    }

    public function approve(ApprovalInstance $instance, int $userId, ?string $comment = null): ApprovalInstance
    {
        if ($instance->status !== 'pending') {
            throw new RuntimeException('Approval already completed.');
        }

        return DB::transaction(function () use ($instance, $userId, $comment) {
            ApprovalAction::create([
                'approval_instance_id' => $instance->id,
                'step_order' => $instance->current_step,
                'user_id' => $userId,
                'action' => 'approved',
                'comment' => $comment,
                'acted_at' => now(),
            ]);

            $steps = ApprovalStep::where('approval_workflow_id', $instance->approval_workflow_id)
                ->orderBy('step_order')
                ->get();

            $maxStep = $steps->max('step_order');

            if ($instance->current_step >= $maxStep) {
                $instance->update(['status' => 'approved']);
                $this->finalizeEntity($instance);
            } else {
                $instance->update(['current_step' => $instance->current_step + 1]);
            }

            return $instance->fresh();
        });
    }

    public function reject(ApprovalInstance $instance, int $userId, ?string $comment = null): ApprovalInstance
    {
        ApprovalAction::create([
            'approval_instance_id' => $instance->id,
            'step_order' => $instance->current_step,
            'user_id' => $userId,
            'action' => 'rejected',
            'comment' => $comment,
            'acted_at' => now(),
        ]);

        $instance->update(['status' => 'rejected']);
        $this->rejectEntity($instance);

        return $instance->fresh();
    }

    protected function finalizeEntity(ApprovalInstance $instance): void
    {
        if ($instance->entity_type === 'leave_request') {
            $leave = LeaveRequest::find($instance->entity_id);
            $leave?->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        }

        if ($instance->entity_type === 'overtime_request') {
            $ot = OvertimeRequest::find($instance->entity_id);
            $ot?->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        }

        if ($instance->entity_type === 'recruitment_request') {
            RecruitmentRequest::where('id', $instance->entity_id)->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        }
    }

    protected function rejectEntity(ApprovalInstance $instance): void
    {
        if ($instance->entity_type === 'leave_request') {
            LeaveRequest::where('id', $instance->entity_id)->update(['status' => 'rejected']);
        }
        if ($instance->entity_type === 'overtime_request') {
            OvertimeRequest::where('id', $instance->entity_id)->update(['status' => 'rejected']);
        }
        if ($instance->entity_type === 'recruitment_request') {
            RecruitmentRequest::where('id', $instance->entity_id)->update(['status' => 'rejected']);
        }
    }

    public function pendingInbox(): array
    {
        $instances = ApprovalInstance::with(['workflow.steps'])
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        if ($instances->isEmpty()) {
            return [];
        }

        $grouped = $instances->groupBy('entity_type');

        $leaves = LeaveRequest::with('employee')
            ->whereIn('id', $grouped->get('leave_request', collect())->pluck('entity_id'))
            ->get()
            ->keyBy('id');

        $overtimes = OvertimeRequest::with('employee')
            ->whereIn('id', $grouped->get('overtime_request', collect())->pluck('entity_id'))
            ->get()
            ->keyBy('id');

        $recruitments = RecruitmentRequest::with(['department', 'position'])
            ->whereIn('id', $grouped->get('recruitment_request', collect())->pluck('entity_id'))
            ->get()
            ->keyBy('id');

        $stepLabels = ApprovalStep::query()
            ->whereIn('approval_workflow_id', $instances->pluck('approval_workflow_id')->unique())
            ->get()
            ->groupBy('approval_workflow_id')
            ->map(fn ($steps) => $steps->keyBy('step_order'));

        return $instances->map(function (ApprovalInstance $instance) use ($leaves, $overtimes, $recruitments, $stepLabels) {
            $entity = match ($instance->entity_type) {
                'leave_request' => $leaves->get($instance->entity_id),
                'overtime_request' => $overtimes->get($instance->entity_id),
                'recruitment_request' => $recruitments->get($instance->entity_id),
                default => null,
            };

            $entityLabel = match ($instance->entity_type) {
                'leave_request' => $entity?->employee?->full_name ?? 'Nghỉ phép',
                'overtime_request' => $entity?->employee?->full_name ?? 'Tăng ca',
                'recruitment_request' => $entity ? "{$entity->code} — {$entity->title}" : 'Yêu cầu tuyển dụng',
                default => 'Yêu cầu #'.$instance->entity_id,
            };

            return [
                'instance' => $instance,
                'entity' => $entity,
                'entity_label' => $entityLabel,
                'entity_type_label' => config("recruitment.entity_type_labels.{$instance->entity_type}", $instance->entity_type),
                'current_step_label' => $stepLabels
                    ->get($instance->approval_workflow_id)
                    ?->get($instance->current_step)
                    ?->label,
            ];
        })->all();
    }
}
