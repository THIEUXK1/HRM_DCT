<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;
use App\Models\Employee;
use App\Models\AttendanceSummary;
use App\Models\PayrollCycle;
use App\Services\Attendance\AttendanceSummaryService;
use App\Services\Payroll\PayrollCycleService;

$c = Company::where('code', 'COMP-001')->first();
$period = now()->format('Y-m');
echo 'Cong ty: '.$c->name.' | NV active: '.Employee::where('company_id', $c->id)->where('is_active', true)->count().PHP_EOL;

$svc = app(AttendanceSummaryService::class);
echo 'Build cong: '.$svc->buildForPeriod($c->id, $period).' NV'.PHP_EOL;
$locked = $svc->lockPeriod($c->id, $period);
echo 'Khoa ky: '.$locked.' ban ghi'.PHP_EOL;

$rows = AttendanceSummary::where('company_id', $c->id)->where('period', $period)
    ->orderBy('employee_id')->get();
foreach ($rows as $r) {
    echo 'NV'.$r->employee_id.': cong='.$r->work_days.' tv='.$r->probation_work_days
        .' ct='.$r->official_work_days.' chuan='.$r->standard_work_days
        .' gio='.$r->actual_work_hours.' ot='.$r->ot_hours
        .' tre='.$r->late_count.' vang='.$r->absent_days.PHP_EOL;
}

$cycle = PayrollCycle::updateOrCreate(
    ['company_id' => $c->id, 'period' => $period],
    [
        'name' => 'Luong thang '.$period,
        'status' => 'draft',
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ]
);
$res = app(PayrollCycleService::class)->calculate($cycle);
echo 'Payroll status: '.$res->status.' | so phieu: '.$res->results->count().PHP_EOL;
foreach ($res->results as $p) {
    echo 'NV'.$p->employee_id.' gross='.number_format($p->gross_salary)
        .' bhxh='.number_format($p->bhxh_employee)
        .' pit='.number_format($p->pit_amount)
        .' net='.number_format($p->net_salary).PHP_EOL;
}
