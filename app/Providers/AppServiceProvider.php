<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAwardDiscipline;
use App\Models\EmployeeTermination;
use App\Models\EmployeeTransfer;
use App\Models\EmploymentContract;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PayrollCycle;
use App\Models\PayrollResult;
use App\Models\Position;
use App\Observers\AuditLogObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Org structure
        Company::observe(AuditLogObserver::class);
        Branch::observe(AuditLogObserver::class);
        Department::observe(AuditLogObserver::class);
        Position::observe(AuditLogObserver::class);

        // Employee data (PII — salary fields will be masked by observer)
        Employee::observe(AuditLogObserver::class);
        EmploymentContract::observe(AuditLogObserver::class);
        EmployeeAwardDiscipline::observe(AuditLogObserver::class);
        EmployeeTransfer::observe(AuditLogObserver::class);
        EmployeeTermination::observe(AuditLogObserver::class);

        // Leave & OT
        LeaveRequest::observe(AuditLogObserver::class);
        OvertimeRequest::observe(AuditLogObserver::class);

        // Payroll (salary fields masked)
        PayrollCycle::observe(AuditLogObserver::class);
        PayrollResult::observe(AuditLogObserver::class);

        // Never self-observe
        AuditLog::observe(AuditLogObserver::class);
    }
}
