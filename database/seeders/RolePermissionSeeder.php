<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'companies.view', 'companies.create', 'companies.edit', 'companies.delete',
            'branches.view', 'branches.create', 'branches.edit', 'branches.delete',
            'departments.view', 'departments.create', 'departments.edit', 'departments.delete',
            'positions.view', 'positions.create', 'positions.edit', 'positions.delete',
            'employees.view', 'employees.create', 'employees.edit', 'employees.delete',
            'employment_contracts.view', 'employment_contracts.create', 'employment_contracts.edit',
            'audit_logs.view',
            'candidates.view', 'candidates.manage',
            'leave.view', 'leave.manage', 'leave.approve',
            'attendance.view', 'attendance.manage',
            'attendance.punch_gps', 'attendance.punch_qr', 'attendance.punch_accounts.manage',
            'payroll.view', 'payroll.manage', 'payroll.approve',
            'training.view', 'training.manage',
            'competency.view', 'competency.manage',
            'performance.view', 'performance.manage',
            'approvals.view', 'approvals.act',
            'bhxh.export',
            'bhxh.manage',
            'users.manage',
            'companies.manage',
            'employees.manage',
            'policy_templates.view',
            'policy_templates.apply',
            'company_policies.view',
            'company_policies.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions); // admin gets ALL permissions

        $hrManager = Role::firstOrCreate(['name' => 'hr_manager']);
        $hrManager->syncPermissions([
            'companies.view', 'companies.manage', 'companies.create', 'companies.edit',
            'branches.view', 'branches.create', 'branches.edit', 'branches.delete',
            'departments.view', 'departments.create', 'departments.edit', 'departments.delete',
            'positions.view', 'positions.create', 'positions.edit', 'positions.delete',
            'employees.view', 'employees.create', 'employees.edit', 'employees.manage',
            'employment_contracts.view', 'employment_contracts.create', 'employment_contracts.edit',
            'candidates.view', 'candidates.manage',
            'leave.view', 'leave.manage', 'leave.approve',
            'attendance.view', 'attendance.manage',
            'attendance.punch_accounts.manage',
            'payroll.view', 'payroll.manage',
            'training.view', 'training.manage',
            'competency.view', 'competency.manage',
            'performance.view', 'performance.manage',
            'approvals.view', 'approvals.act',
            'bhxh.export',
            'bhxh.manage',
            'company_policies.view',
            'company_policies.manage',
            'users.manage',
        ]);

        $auditor = Role::firstOrCreate(['name' => 'auditor']);
        $auditor->syncPermissions([
            'companies.view', 'branches.view', 'departments.view', 'positions.view',
            'employees.view', 'employment_contracts.view', 'audit_logs.view',
            'payroll.view', 'leave.view', 'attendance.view', 'bhxh.export',
        ]);

        $manager = Role::firstOrCreate(['name' => 'department_manager']);
        $manager->syncPermissions([
            'employees.view', 'leave.view', 'leave.approve',
            'attendance.view', 'performance.view', 'approvals.view', 'approvals.act',
        ]);

        $employee = Role::firstOrCreate(['name' => 'employee']);
        $employee->syncPermissions([
            'leave.view', 'training.view', 'performance.view',
        ]);

        $deptSecretary = Role::firstOrCreate(['name' => 'department_secretary']);
        $deptSecretary->syncPermissions([
            'employees.view',
            'leave.view', 'leave.manage', 'leave.approve',
            'attendance.view', 'attendance.manage',
            'approvals.view', 'approvals.act',
        ]);

        $payrollSpec = Role::firstOrCreate(['name' => 'payroll_specialist']);
        $payrollSpec->syncPermissions([
            'payroll.view', 'payroll.manage', 'payroll.approve',
            'approvals.view',
        ]);

        $insuranceSpec = Role::firstOrCreate(['name' => 'insurance_specialist']);
        $insuranceSpec->syncPermissions([
            'bhxh.export', 'bhxh.manage',
            'approvals.view',
        ]);

        $recruitmentSpec = Role::firstOrCreate(['name' => 'recruitment_specialist']);
        $recruitmentSpec->syncPermissions([
            'candidates.view', 'candidates.manage',
            'approvals.view',
        ]);
    }
}
