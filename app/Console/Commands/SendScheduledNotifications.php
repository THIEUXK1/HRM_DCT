<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendScheduledNotifications extends Command
{
    protected $signature   = 'notifications:send-scheduled';
    protected $description = 'Gửi thông báo tự động hàng ngày: hết hạn HĐ, sinh nhật, hết thử việc, OT vượt mức';

    public function handle(): int
    {
        $this->info('[1/5] Kiểm tra hợp đồng sắp hết hạn...');
        $c1 = NotificationService::checkExpiringContracts(daysAhead: 30);
        $this->line("  → {$c1} thông báo đã gửi");

        $this->info('[2/5] Kiểm tra hợp đồng đã hết hạn...');
        $c2 = NotificationService::checkExpiredContracts();
        $this->line("  → {$c2} thông báo đã gửi");

        $this->info('[3/5] Gửi lời chúc sinh nhật...');
        $c3 = NotificationService::checkBirthdays();
        $this->line("  → {$c3} thông báo đã gửi");

        $this->info('[4/5] Kiểm tra nhân viên sắp hết thử việc...');
        $c4 = NotificationService::checkProbationEnding(daysAhead: 14);
        $this->line("  → {$c4} thông báo đã gửi");

        $this->info('[5/5] Kiểm tra OT vượt giới hạn pháp luật...');
        $c5 = NotificationService::checkOvertimeCapBreaches();
        $this->line("  → {$c5} thông báo công ty đã gửi");

        $total = $c1 + $c2 + $c3 + $c4 + $c5;
        $this->info("Hoàn thành. Tổng: {$total} thông báo.");

        return Command::SUCCESS;
    }
}
