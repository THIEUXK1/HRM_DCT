<?php

namespace App\Console\Commands;

use App\Models\AttendanceSource;
use App\Services\Attendance\ZKTimeSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncZKTimeScheduledCommand extends Command
{
    protected $signature = 'attendance:sync-zktime-scheduled';

    protected $description = 'Scheduled sync for all active ZKTime SQL sources';

    public function handle(ZKTimeSyncService $syncService): int
    {
        $this->info('Bắt đầu chạy đồng bộ ZKTime theo lịch...');

        $sources = AttendanceSource::where('is_active', true)->get();

        if ($sources->isEmpty()) {
            $this->info('Không có nguồn ZKTime hoạt động nào.');
            return 0;
        }

        // Sync for yesterday and today to capture full day and overlap shifts
        $from = Carbon::yesterday()->toDateString();
        $to = Carbon::today()->toDateString();

        foreach ($sources as $source) {
            $this->info("Đang đồng bộ nguồn: {$source->name} (Company: {$source->company_id})...");
            try {
                $result = $syncService->sync($source, $from, $to, false);
                $this->info("Đồng bộ thành công nguồn #{$source->id}. Đã đọc: {$result['total_read']}, Thêm: {$result['inserted']}, Skip: {$result['skipped']}, Chưa map: {$result['unmapped']}");
            } catch (\Throwable $e) {
                Log::error("Lỗi đồng bộ scheduled ZKTime nguồn #{$source->id} ({$source->name}): " . $e->getMessage());
                $this->error("Lỗi đồng bộ nguồn #{$source->id}: " . $e->getMessage());
            }
        }

        $this->info('Hoàn thành chạy lịch đồng bộ ZKTime.');
        return 0;
    }
}
