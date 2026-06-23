<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Hr\ExternalHrSyncService;
use Illuminate\Console\Command;

class SyncExternalHrCommand extends Command
{
    protected $signature   = 'ehr:sync-external {--company= : Company ID to sync (defaults to all active companies)}';
    protected $description = 'Sync employee data from legacy EHR system (bestpacific.com API)';

    public function handle(ExternalHrSyncService $service): int
    {
        $companyId = $this->option('company');

        $companies = $companyId
            ? Company::where('id', $companyId)->get()
            : Company::where('is_active', true)->get();

        if ($companies->isEmpty()) {
            $this->error('Không tìm thấy công ty hợp lệ.');
            return self::FAILURE;
        }

        foreach ($companies as $company) {
            $this->info("Đồng bộ: {$company->name} (ID={$company->id})");

            try {
                $stats = $service->sync($company->id);
                $this->table(
                    ['Tạo mới', 'Cập nhật', 'Bỏ qua', 'Lỗi'],
                    [[$stats['created'], $stats['updated'], $stats['skipped'], count($stats['errors'])]]
                );
                if ($stats['errors']) {
                    foreach ($stats['errors'] as $err) {
                        $this->warn("  ⚠ {$err}");
                    }
                }
            } catch (\Throwable $e) {
                $this->error("Lỗi: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
