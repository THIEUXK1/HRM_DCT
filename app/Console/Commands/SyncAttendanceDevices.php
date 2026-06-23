<?php

namespace App\Console\Commands;

use App\Models\AttendanceDevice;
use App\Services\Attendance\AttendanceDeviceSyncService;
use Illuminate\Console\Command;

class SyncAttendanceDevices extends Command
{
    protected $signature = 'attendance:sync-devices
                            {--device= : Mã code của thiết bị cụ thể (bỏ qua = sync tất cả)}
                            {--company= : Chỉ sync thiết bị của company_id này}';

    protected $description = 'Lấy log chấm công từ máy ZKTeco qua TCP';

    public function handle(AttendanceDeviceSyncService $syncService): int
    {
        $query = AttendanceDevice::query()
            ->where('is_active', true)
            ->whereNotNull('ip_address');

        if ($code = $this->option('device')) {
            $query->where('code', $code);
        }

        if ($companyId = $this->option('company')) {
            $query->where('company_id', $companyId);
        }

        $devices = $query->get();

        if ($devices->isEmpty()) {
            $this->warn('Không có thiết bị ZKTeco nào cần sync.');
            return self::SUCCESS;
        }

        $this->info("Bắt đầu sync {$devices->count()} thiết bị...");

        foreach ($devices as $device) {
            $this->line("  → [{$device->code}] {$device->name} ({$device->ip_address}:{$device->port})");
            $result = $syncService->sync($device);

            if ($result['errors'] === 1 && $result['synced'] === 0) {
                $this->error("    ✗ {$result['message']}");
            } else {
                $this->info("    ✓ {$result['message']}");
            }
        }

        $this->info('Hoàn tất sync chấm công.');
        return self::SUCCESS;
    }
}
