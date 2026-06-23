<?php

namespace App\Services\Payroll;

use App\Models\PayrollCycle;
use Carbon\Carbon;
use RuntimeException;

class PayrollCycleStoreService
{
    /**
     * @return array{
     *   period: string,
     *   run_number: int,
     *   has_locked_run: bool,
     *   latest_cycle: PayrollCycle|null,
     *   can_create_new: bool,
     *   block_reason: string|null
     * }
     */
    public function periodStatus(int $companyId, string $period): array
    {
        $cycles = PayrollCycle::where('company_id', $companyId)
            ->where('period', $period)
            ->orderByDesc('run_number')
            ->get();

        $latest = $cycles->first();
        $hasLocked = $cycles->contains(fn (PayrollCycle $c) => $c->status === 'locked');
        $blockReason = null;
        $canCreate = true;

        if ($latest && in_array($latest->status, ['draft', 'calculated'], true)) {
            $canCreate = false;
            $blockReason = "Đã có bảng lương «{$this->displayLabel($latest)}» chưa khóa. Mở bảng đó và bấm «Tính lương» hoặc khóa trước khi tạo bản mới.";
        }

        return [
            'period' => $period,
            'next_run_number' => $latest
                ? (int) $latest->run_number + ($latest->status === 'locked' ? 1 : 0)
                : 1,
            'has_locked_run' => $hasLocked,
            'latest_cycle' => $latest,
            'can_create_new' => $canCreate,
            'block_reason' => $blockReason,
            'cycles' => $cycles->map(fn (PayrollCycle $c) => [
                'id' => $c->id,
                'run_number' => $c->run_number,
                'label' => $this->displayLabel($c),
                'status' => $c->status,
                'locked_at' => $c->locked_at,
            ])->values()->all(),
        ];
    }

    public function create(int $companyId, string $period, ?string $revisionNote = null): PayrollCycle
    {
        $status = $this->periodStatus($companyId, $period);
        if (! $status['can_create_new']) {
            throw new RuntimeException($status['block_reason'] ?? 'Không thể tạo bảng lương mới cho tháng này.');
        }

        $latest = $status['latest_cycle'];
        $runNumber = $latest ? (int) $latest->run_number + 1 : 1;
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $label = $runNumber > 1 ? "Lần tính {$runNumber}" : null;
        if ($revisionNote) {
            $label = $label ? "{$label} — {$revisionNote}" : $revisionNote;
        }

        return PayrollCycle::create([
            'company_id' => $companyId,
            'period' => $period,
            'run_number' => $runNumber,
            'label' => $label,
            'revision_note' => $revisionNote,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => 'draft',
        ]);
    }

    public function displayLabel(PayrollCycle $cycle): string
    {
        if ($cycle->label) {
            return $cycle->period.' · '.$cycle->label;
        }

        if ((int) $cycle->run_number > 1) {
            return $cycle->period.' · Lần '.(int) $cycle->run_number;
        }

        return $cycle->period;
    }
}
