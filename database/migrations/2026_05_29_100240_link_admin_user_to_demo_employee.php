<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Liên kết tài khoản admin demo với hồ sơ NV để mở được Cổng ESS.
 */
return new class extends Migration
{
    public function up(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->first();
        $employee = Employee::query()->where('employee_code', 'EMP-001')->first();

        if ($admin && $employee && ! $admin->employee_id) {
            $admin->update(['employee_id' => $employee->id]);
        }
    }

    public function down(): void
    {
        User::query()->where('email', 'admin@example.com')->update(['employee_id' => null]);
    }
};
