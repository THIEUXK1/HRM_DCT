<?php

namespace App\Console\Commands;

use App\Models\AttendanceSource;
use App\Services\Attendance\ZKTimeSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncZKTimeCommand extends Command
{
    protected $signature = 'attendance:sync-zktime
                            {--source-id= : Sync a specific ZKTime source by ID}
                            {--company-id= : Sync all sources for a specific company by ID}
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD)}
                            {--dry-run : Perform dry-run without writing to database}
                            {--force : Force sync even if errors occurred}';

    protected $description = 'Sync attendance records from ZKTime SQL Server databases';

    public function handle(ZKTimeSyncService $syncService): int
    {
        $sourceId = $this->option('source-id');
        $companyId = $this->option('company-id');
        $from = $this->option('from');
        $to = $this->option('to');
        $dryRun = $this->option('dry-run');

        if (!$sourceId && !$companyId) {
            $this->error('Vui lòng cung cấp --source-id hoặc --company-id.');
            return 1;
        }

        // Default to current month if dates not provided
        $fromDate = $from ?: Carbon::now()->startOfMonth()->toDateString();
        $toDate = $to ?: Carbon::now()->toDateString();

        // Resolve sources
        $query = AttendanceSource::query()->where('is_active', true);
        if ($sourceId) {
            $query->where('id', $sourceId);
        } else {
            $query->where('company_id', $companyId);
        }

        $sources = $query->get();

        if ($sources->isEmpty()) {
            $this->warn('Không tìm thấy nguồn chấm công ZKTime hoạt động nào phù hợp.');
            return 0;
        }

        $this->info("Bắt đầu đồng bộ chấm công ZKTime từ {$fromDate} đến {$toDate}...");
        if ($dryRun) {
            $this->comment('=== CHẾ ĐỘ CHẠY THỬ (DRY-RUN) — KHÔNG LƯU DỮ LIỆU ===');
        }

        foreach ($sources as $source) {
            $this->line("--------------------------------------------------");
            $this->info("Nguồn: {$source->name} (Company ID: {$source->company_id})");

            try {
                $result = $syncService->sync($source, $fromDate, $toDate, $dryRun);

                if ($dryRun) {
                    $this->line("  Tổng số log đọc được: " . $result['total_read']);
                    $this->line("  Số log mới sẽ thêm:   " . $result['new_logs']);
                    $this->line("  Số log trùng sẽ skip: " . $result['duplicates']);
                    $this->line("  Số log chưa map NV:   " . $result['unmapped']);
                } else {
                    $this->line("  Tổng số log đọc được: " . $result['total_read']);
                    $this->line("  Số log đã lưu:        " . $result['inserted']);
                    $this->line("  Số log đã skip (trùng):" . $result['skipped']);
                    $this->line("  Số log chưa map NV:   " . $result['unmapped']);
                }
            } catch (\Throwable $e) {
                $this->error("Lỗi đồng bộ nguồn #{$source->id} ({$source->name}): " . $e->getMessage());
            }
        }

        $this->line("--------------------------------------------------");
        $this->info('Hoàn thành quá trình đồng bộ.');
        return 0;
    }
}
