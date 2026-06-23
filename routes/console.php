<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync từ hệ thống EHR cũ mỗi đêm lúc 2:00 sáng
Schedule::command('ehr:sync-external')->dailyAt('02:00')->withoutOverlapping();

// Lấy log chấm công từ máy ZKTeco: 9h sáng và 20h tối
Schedule::command('attendance:sync-devices')->twiceDaily(9, 20)->withoutOverlapping();

// Đồng bộ chấm công từ cơ sở dữ liệu ZKTime SQL hằng ngày lúc 09:00 sáng
Schedule::command('attendance:sync-zktime-scheduled')->dailyAt('09:00')->withoutOverlapping();
