<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(InitialHrDataSeeder::class);
        $this->call(HcmPlatformSeeder::class);
        $this->call(HcmExtendedSeeder::class);
        $this->call(MultiCompanyDemoSeeder::class);
        $this->call(SampleEmployeesSeeder::class);

        $tenant = \App\Models\Tenant::first();
        $company = \App\Models\Company::first();

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('Admin@123'),
                'tenant_id' => $tenant?->id,
                'default_company_id' => $company?->id,
            ]
        );

        if ($company) {
            $admin->companies()->syncWithoutDetaching([$company->id]);
        }

        $admin->assignRole('admin');

        $employee = \App\Models\Employee::query()->where('employee_code', 'EMP-001')->first();
        if ($employee && ! $admin->employee_id) {
            $admin->update(['employee_id' => $employee->id]);
        }
    }
}
