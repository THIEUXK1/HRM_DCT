<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/app');
});

// SPA mount tại /app — tránh 404 khi mở /payroll, /employees, …
$spaEntryPaths = [
    'payroll', 'employees', 'attendance', 'leave-requests', 'settings',
    'contracts', 'bhxh', 'reports', 'self-service', 'organization',
];
foreach ($spaEntryPaths as $segment) {
    Route::redirect('/'.$segment, '/app/'.$segment);
}

Route::view('/app/{any?}', 'app')->where('any', '.*');
