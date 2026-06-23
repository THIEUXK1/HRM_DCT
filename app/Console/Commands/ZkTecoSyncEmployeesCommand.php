<?php

namespace App\Console\Commands;

use App\Models\AttendanceDevice;
use App\Services\Attendance\ZKTecoSyncService;
use Illuminate\Console\Command;

class ZkTecoSyncEmployeesCommand extends Command
{
    protected $signature = 'zkteco:sync-employees
                            {--employee= : Sync a single employee by ID}
                            {--department= : Sync active employees in department by ID}
                            {--all-active : Sync all active employees}
                            {--device= : Target a single device by ID}
                            {--devices= : Target device IDs (comma separated or "all")}
                            {--dry-run : Perform a dry-run check without updating hardware}
                            {--force : Force overwrite/update existing user data on devices}';

    protected $description = 'Synchronize employee profiles to ZKTeco hardware devices';

    public function handle(ZKTecoSyncService $syncService): int
    {
        $employeeId = $this->option('employee');
        $departmentId = $this->option('department');
        $allActive = $this->option('all-active');

        $deviceId = $this->option('device');
        $devicesOpt = $this->option('devices');

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // 1. Determine sync mode
        $mode = 'all';
        $employeeIds = [];
        if ($employeeId) {
            $mode = 'manual';
            $employeeIds = [$employeeId];
        } elseif ($departmentId) {
            $mode = 'department';
        } elseif (!$allActive) {
            $this->error("Vui lòng cung cấp ít nhất một bộ lọc: --employee=ID, --department=ID, hoặc --all-active");
            return 1;
        }

        // 2. Resolve target device IDs
        $targetDeviceIds = [];
        if ($deviceId) {
            $targetDeviceIds = [(int) $deviceId];
        } elseif ($devicesOpt) {
            if ($devicesOpt === 'all') {
                $targetDeviceIds = AttendanceDevice::where('is_active', true)
                    ->whereNotNull('ip_address')
                    ->pluck('id')
                    ->all();
            } else {
                $targetDeviceIds = array_map('intval', explode(',', $devicesOpt));
            }
        } else {
            $this->error("Vui lòng cung cấp thiết bị đích: --device=ID hoặc --devices=all|IDs");
            return 1;
        }

        if (empty($targetDeviceIds)) {
            $this->error("Không tìm thấy thiết bị đích hoạt động nào phù hợp.");
            return 1;
        }

        // We assume company ID = 1 for default CLI execution (or resolve from first device)
        $firstDevice = AttendanceDevice::find($targetDeviceIds[0]);
        $companyId = $firstDevice ? $firstDevice->company_id : 1;

        $options = [
            'overwrite_mode' => $force ? 'update' : 'skip',
        ];

        if ($dryRun) {
            $this->info("=== CHẾ ĐỘ KIỂM TRA TRƯỚC (DRY-RUN) ===");
            $report = $syncService->dryRunReport(
                companyId: $companyId,
                deviceIds: $targetDeviceIds,
                mode: $mode,
                departmentId: $departmentId ? (int) $departmentId : null,
                employeeIds: $employeeIds,
                filters: [],
                options: $options
            );

            $this->line("Tổng số nhân viên trong danh sách: " . $report['total_employees']);
            $this->line("Tổng số thiết bị sẽ nhận dữ liệu: " . $report['total_devices']);

            if (!empty($report['missing_biometric'])) {
                $this->warn("Cảnh báo: Có " . count($report['missing_biometric']) . " nhân sự thiếu mã vân tay (sẽ bị bỏ qua):");
                foreach ($report['missing_biometric'] as $item) {
                    $this->line("  - [{$item['employee_code']}] {$item['full_name']}");
                }
            }

            foreach ($report['devices_breakdown'] as $db) {
                $this->line("--------------------------------------------------");
                $this->info("Thiết bị: {$db['device_name']} ({$db['ip_address']}) - Trạng thái: " . ($db['is_online'] ? 'ONLINE' : 'OFFLINE'));
                if (!$db['is_online']) {
                    $this->error("  Lỗi kết nối: " . $db['error_message']);
                    continue;
                }

                $this->line("  - Số nhân sự sẽ tạo mới: " . $db['will_create']);
                $this->line("  - Số nhân sự sẽ cập nhật: " . $db['will_update']);
                $this->line("  - Số nhân sự sẽ bỏ qua (đã có): " . $db['skipped_existing']);
            }
            return 0;
        }

        // Actual Run: trigger sync batch and queue job
        $this->info("Khởi tạo batch đồng bộ trên hệ thống...");
        $batch = $syncService->runSync(
            companyId: $companyId,
            deviceIds: $targetDeviceIds,
            mode: $mode,
            departmentId: $departmentId ? (int) $departmentId : null,
            employeeIds: $employeeIds,
            filters: [],
            options: $options,
            requestedBy: null // CLI
        );

        $this->info("✓ Đã lên lịch đồng bộ thành công! Batch ID: #{$batch->id}");
        $this->comment("Chạy queue worker ('php artisan queue:work') để bắt đầu đẩy dữ liệu.");
        $this->comment("Theo dõi tiến trình bằng lệnh: 'php artisan zkteco:sync-batch {$batch->id}'");

        return 0;
    }
}
