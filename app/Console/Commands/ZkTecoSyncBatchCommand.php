<?php

namespace App\Console\Commands;

use App\Models\ZkTecoSyncBatch;
use Illuminate\Console\Command;

class ZkTecoSyncBatchCommand extends Command
{
    protected $signature = 'zkteco:sync-batch {batch_id} {--show-logs : Display detailed log for each employee sync action}';
    protected $description = 'Display the progress status and logs of a ZKTeco employee synchronization batch';

    public function handle(): int
    {
        $batchId = $this->argument('batch_id');
        $batch = ZkTecoSyncBatch::with(['requestedByUser', 'logs.device', 'logs.employee'])->find($batchId);

        if (!$batch) {
            $this->error("Không tìm thấy batch đồng bộ ID #{$batchId}");
            return 1;
        }

        $this->info("=== THÔNG TIN BATCH ĐỒNG BỘ #{$batch->id} ===");
        $this->line("Loại đồng bộ: " . strtoupper($batch->sync_type));
        $this->line("Trạng thái:   " . strtoupper($batch->status));
        $this->line("Chế độ thử:  " . ($batch->dry_run ? 'CÓ (DRY-RUN)' : 'KHÔNG (REAL-RUN)'));
        $this->line("Khởi tạo bởi: " . ($batch->requestedByUser ? $batch->requestedByUser->name : 'Hệ thống (CLI)'));
        $this->line("Bắt đầu lúc:  " . ($batch->started_at ? $batch->started_at->format('Y-m-d H:i:s') : 'Chưa bắt đầu'));
        $this->line("Kết thúc lúc: " . ($batch->finished_at ? $batch->finished_at->format('Y-m-d H:i:s') : 'Chưa kết thúc'));

        $this->line("--------------------------------------------------");
        $this->line("Thiết bị đích: " . count($batch->target_device_ids) . " thiết bị");
        $this->line("Số nhân sự:    " . $batch->total_employees);
        
        $totalActions = $batch->total_employees * count($batch->target_device_ids);
        $loggedActions = $batch->logs()->count();
        
        $percent = $totalActions > 0 ? round(($loggedActions / $totalActions) * 100) : 0;
        $this->info("Tiến trình:  {$percent}% ({$loggedActions}/{$totalActions} hành động được ghi nhận)");

        $this->line("Kết quả:");
        $this->info("  - THÀNH CÔNG: " . $batch->success_count);
        $this->error("  - THẤT BẠI:   " . $batch->failed_count);
        $this->warn("  - BỎ QUA:     " . $batch->skipped_count);

        if ($this->option('show-logs') || $loggedActions > 0) {
            $this->line("--------------------------------------------------");
            $this->info("Chi tiết nhật ký đồng bộ:");
            
            $headers = ['Thiết bị', 'Nhân viên', 'Mã NV', 'Hành động', 'Trạng thái', 'Thông điệp'];
            $rows = [];

            foreach ($batch->logs as $log) {
                $deviceName = $log->device ? "{$log->device->name} ({$log->device->ip_address})" : "ID {$log->device_id}";
                $employeeName = $log->employee ? $log->employee->full_name : 'N/A';
                
                $statusColor = $log->status;
                if ($log->status === 'success') {
                    $statusText = 'SUCCESS';
                } elseif ($log->status === 'failed') {
                    $statusText = 'FAILED';
                } else {
                    $statusText = 'SKIPPED';
                }

                $rows[] = [
                    $deviceName,
                    $employeeName,
                    $log->employee_code,
                    strtoupper($log->action),
                    $statusText,
                    $log->message ?: ($log->error_detail ? substr($log->error_detail, 0, 50) . '...' : '')
                ];
            }

            if (empty($rows)) {
                $this->comment("Chưa có chi tiết nhật ký nào được ghi nhận.");
            } else {
                $this->table($headers, $rows);
            }
        }

        return 0;
    }
}
