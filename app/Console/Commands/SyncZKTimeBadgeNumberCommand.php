<?php

namespace App\Console\Commands;

use App\Models\AttendanceSource;
use App\Services\Attendance\ZKTimeSyncService;
use Illuminate\Console\Command;

class SyncZKTimeBadgeNumberCommand extends Command
{
    protected $signature = 'zktime:sync-badge-number
                            {--source-id= : Sync a specific ZKTime source by ID}
                            {--company-id= : Sync all sources for a specific company by ID}
                            {--dry-run : Perform dry-run without writing to database}
                            {--force : Force overwrite existing fingerprint codes}';

    protected $description = 'Sync employee fingerprint code (Mã vân tay / Badgenumber) from ZKTime database to HRM profiles';

    public function handle(ZKTimeSyncService $syncService): int
    {
        $sourceId = $this->option('source-id');
        $companyId = $this->option('company-id');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if (!$sourceId && !$companyId) {
            $this->error('Vui lòng cung cấp --source-id hoặc --company-id.');
            return 1;
        }

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

        $this->info("Bắt đầu đồng bộ Mã vân tay từ ZKTime...");
        if ($dryRun) {
            $this->comment('=== CHẾ ĐỘ CHẠY THỬ (DRY-RUN) — KHÔNG LƯU DỮ LIỆU ===');
        }

        foreach ($sources as $source) {
            $this->line("--------------------------------------------------");
            $this->info("Nguồn: {$source->name} (Company ID: {$source->company_id})");

            try {
                $result = $syncService->syncFingerprintCodes($source, $dryRun, $force);

                $this->line("  Tổng số user đọc từ ZKTime: " . $result['total_read']);
                $this->line("  Số nhân viên ERP match được: " . $result['matched_count']);
                $this->line("  Số nhân viên chưa match được: " . $result['unmatched_count']);
                $this->line("  Số bản ghi sẽ/đã được cập nhật: " . $result['updated_count']);
                $this->line("  Số bản ghi bị bỏ qua vì đã có Mã vân tay: " . $result['skipped_count']);
                
                if (!empty($result['warnings'])) {
                    $this->warn("  Danh sách cảnh báo:");
                    foreach ($result['warnings'] as $warning) {
                        $this->line("    - {$warning}");
                    }
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
