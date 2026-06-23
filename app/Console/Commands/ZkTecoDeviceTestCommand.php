<?php

namespace App\Console\Commands;

use App\Models\AttendanceDevice;
use App\Services\Attendance\ZKTecoService;
use Illuminate\Console\Command;

class ZkTecoDeviceTestCommand extends Command
{
    protected $signature = 'zkteco:device-test {device_id}';
    protected $description = 'Test TCP/IP connection and retrieve details from ZKTeco device';

    public function handle(): int
    {
        $deviceId = $this->argument('device_id');
        $device = AttendanceDevice::find($deviceId);

        if (!$device) {
            $this->error("Không tìm thấy thiết bị ID #{$deviceId}");
            return 1;
        }

        if (!$device->ip_address) {
            $this->error("Thiết bị chưa được cấu hình địa chỉ IP.");
            return 1;
        }

        $this->info("Đang kiểm tra kết nối tới thiết bị '{$device->name}' ({$device->ip_address}:{$device->port})...");

        try {
            $zk = new ZKTecoService(
                host: $device->ip_address,
                port: $device->port ?? 4370,
                password: $device->comm_key ?? '',
                timeout: 8
            );

            $this->comment("Đang thử kết nối...");
            $zk->connect();
            $this->info("✓ Kết nối thành công!");

            $sn = $zk->getSerialNumber();
            $version = $zk->getVersion();

            $this->line("  - Serial Number: " . ($sn ?: 'Không rõ'));
            $this->line("  - Firmware Version: " . ($version ?: 'Không rõ'));

            $zk->disconnect();

            // Update in database
            $device->update([
                'serial_number' => $sn,
                'last_connected_at' => now(),
                'sync_status' => 'success',
                'sync_message' => 'Test kết nối thành công từ CLI.',
            ]);

            return 0;
        } catch (\Throwable $e) {
            $this->error("✗ Kết nối thất bại: " . $e->getMessage());
            $device->update([
                'sync_status' => 'failed',
                'sync_message' => 'Lỗi kết nối từ CLI: ' . $e->getMessage(),
            ]);
            return 1;
        }
    }
}
