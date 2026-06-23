<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\GenerateApiToken;
use App\Console\Commands\SendScheduledNotifications;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        GenerateApiToken::class,
        SendScheduledNotifications::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Run daily at 7:00 AM — check contracts, birthdays, probation
        $schedule->command('notifications:send-scheduled')
            ->dailyAt('07:00')
            ->withoutOverlapping()
            ->runInBackground();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
