<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkScheduleGroup;
use App\Services\Attendance\WorkScheduleSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): array
    {
        Role::firstOrCreate(['name' => 'admin']);

        $tenant = Tenant::create(['code' => 'T1', 'name' => 'Tenant 1']);
        $company = Company::create([
            'tenant_id' => $tenant->id,
            'code' => 'C1',
            'name' => 'Cty test',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('Admin@123'),
            'default_company_id' => $company->id,
        ]);
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'tok-'.uniqid()])->save();

        return [$user, $company];
    }

    public function test_seed_defaults_creates_production_and_non_production_groups(): void
    {
        [$user, $company] = $this->adminUser();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->post('/api/v1/work-schedules/seed-defaults');

        $response->assertOk();
        $this->assertDatabaseHas('work_schedule_groups', [
            'company_id' => $company->id,
            'group_type' => 'production',
        ]);
        $this->assertDatabaseHas('work_schedule_groups', [
            'company_id' => $company->id,
            'group_type' => 'non_production',
        ]);
    }

    public function test_can_assign_employee_to_schedule(): void
    {
        [$user, $company] = $this->adminUser();
        app(WorkScheduleSetupService::class)->seedDefaults($company->id);

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV001',
            'first_name' => 'A',
            'last_name' => 'B',
            'full_name' => 'B A',
            'email' => 'nv001@test.local',
            'is_active' => true,
        ]);

        $group = WorkScheduleGroup::where('company_id', $company->id)->where('group_type', 'non_production')->first();
        $pattern = $group->patterns()->first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->post('/api/v1/work-schedules/assignments', [
            'employee_id' => $employee->id,
            'work_schedule_group_id' => $group->id,
            'work_schedule_pattern_id' => $pattern->id,
            'effective_from' => '2026-05-01',
            'weekend_swap_enabled' => false,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('employee_work_schedules', [
            'employee_id' => $employee->id,
            'work_schedule_group_id' => $group->id,
        ]);
    }

    public function test_bulk_assign_by_department(): void
    {
        [$user, $company] = $this->adminUser();
        app(WorkScheduleSetupService::class)->seedDefaults($company->id);

        $department = \App\Models\Department::create([
            'company_id' => $company->id,
            'branch_id' => \App\Models\Branch::create([
                'company_id' => $company->id,
                'code' => 'BR1',
                'name' => 'Chi nhánh 1',
            ])->id,
            'code' => 'SX',
            'name' => 'Phòng SX',
        ]);

        $employee = Employee::create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'employee_code' => 'NV002',
            'first_name' => 'C',
            'last_name' => 'D',
            'full_name' => 'D C',
            'email' => 'nv002@test.local',
            'is_active' => true,
        ]);

        $group = WorkScheduleGroup::where('company_id', $company->id)->where('group_type', 'production')->first();
        $pattern = $group->patterns()->first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->post('/api/v1/work-schedules/assignments/bulk', [
            'department_id' => $department->id,
            'work_schedule_group_id' => $group->id,
            'work_schedule_pattern_id' => $pattern->id,
            'effective_from' => '2026-06-01',
            'weekend_swap_enabled' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.assigned', 1);
        $this->assertDatabaseHas('employee_work_schedules', [
            'employee_id' => $employee->id,
            'work_schedule_group_id' => $group->id,
        ]);
    }

    public function test_bulk_assign_by_multiple_departments(): void
    {
        [$user, $company] = $this->adminUser();
        app(WorkScheduleSetupService::class)->seedDefaults($company->id);

        $branchId = \App\Models\Branch::create([
            'company_id' => $company->id,
            'code' => 'BR1',
            'name' => 'Chi nhánh 1',
        ])->id;

        $dept1 = \App\Models\Department::create([
            'company_id' => $company->id,
            'branch_id' => $branchId,
            'code' => 'D1',
            'name' => 'Dept 1',
        ]);
        $dept2 = \App\Models\Department::create([
            'company_id' => $company->id,
            'branch_id' => $branchId,
            'code' => 'D2',
            'name' => 'Dept 2',
        ]);

        $emp1 = Employee::create([
            'company_id' => $company->id,
            'department_id' => $dept1->id,
            'employee_code' => 'NV01',
            'first_name' => 'A',
            'last_name' => 'B',
            'full_name' => 'B A',
            'email' => 'nv01@test.local',
            'is_active' => true,
        ]);
        $emp2 = Employee::create([
            'company_id' => $company->id,
            'department_id' => $dept2->id,
            'employee_code' => 'NV02',
            'first_name' => 'C',
            'last_name' => 'D',
            'full_name' => 'D C',
            'email' => 'nv02@test.local',
            'is_active' => true,
        ]);

        $group = WorkScheduleGroup::where('company_id', $company->id)->where('group_type', 'production')->first();
        $pattern = $group->patterns()->first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->post('/api/v1/work-schedules/assignments/bulk', [
            'department_ids' => [$dept1->id, $dept2->id],
            'shift_id' => $pattern->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.assigned', 2);
        $this->assertDatabaseHas('employee_work_schedules', [
            'employee_id' => $emp1->id,
            'work_schedule_pattern_id' => $pattern->id,
        ]);
        $this->assertDatabaseHas('employee_work_schedules', [
            'employee_id' => $emp2->id,
            'work_schedule_pattern_id' => $pattern->id,
        ]);
    }

    public function test_bulk_assign_by_multiple_employees(): void
    {
        [$user, $company] = $this->adminUser();
        app(WorkScheduleSetupService::class)->seedDefaults($company->id);

        $emp1 = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV03',
            'first_name' => 'E',
            'last_name' => 'F',
            'full_name' => 'F E',
            'email' => 'nv03@test.local',
            'is_active' => true,
        ]);
        $emp2 = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV04',
            'first_name' => 'G',
            'last_name' => 'H',
            'full_name' => 'H G',
            'email' => 'nv04@test.local',
            'is_active' => true,
        ]);

        $group = WorkScheduleGroup::where('company_id', $company->id)->where('group_type', 'non_production')->first();
        $pattern = $group->patterns()->first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->post('/api/v1/work-schedules/assignments/bulk', [
            'employee_ids' => [$emp1->id, $emp2->id],
            'shift_id' => $pattern->id,
            'start_date' => '2026-06-01',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.assigned', 2);
    }

    public function test_bulk_assign_deduplicates_employees(): void
    {
        [$user, $company] = $this->adminUser();
        app(WorkScheduleSetupService::class)->seedDefaults($company->id);

        $branchId = \App\Models\Branch::create([
            'company_id' => $company->id,
            'code' => 'BR1',
            'name' => 'Chi nhánh 1',
        ])->id;

        $dept = \App\Models\Department::create([
            'company_id' => $company->id,
            'branch_id' => $branchId,
            'code' => 'D1',
            'name' => 'Dept 1',
        ]);

        $emp1 = Employee::create([
            'company_id' => $company->id,
            'department_id' => $dept->id,
            'employee_code' => 'NV05',
            'first_name' => 'I',
            'last_name' => 'J',
            'full_name' => 'J I',
            'email' => 'nv05@test.local',
            'is_active' => true,
        ]);

        $group = WorkScheduleGroup::where('company_id', $company->id)->where('group_type', 'non_production')->first();
        $pattern = $group->patterns()->first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->post('/api/v1/work-schedules/assignments/bulk', [
            'department_ids' => [$dept->id],
            'employee_ids' => [$emp1->id],
            'shift_id' => $pattern->id,
            'start_date' => '2026-06-01',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.assigned', 1);
    }

    public function test_bulk_assign_empty_returns_422(): void
    {
        [$user, $company] = $this->adminUser();
        app(WorkScheduleSetupService::class)->seedDefaults($company->id);

        $group = WorkScheduleGroup::where('company_id', $company->id)->where('group_type', 'non_production')->first();
        $pattern = $group->patterns()->first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->post('/api/v1/work-schedules/assignments/bulk', [
            'department_ids' => [],
            'employee_ids' => [],
            'shift_id' => $pattern->id,
            'start_date' => '2026-06-01',
        ]);

        $response->assertStatus(422);
    }

    public function test_bulk_assign_skips_duplicates(): void
    {
        [$user, $company] = $this->adminUser();
        app(WorkScheduleSetupService::class)->seedDefaults($company->id);

        $emp = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV06',
            'first_name' => 'K',
            'last_name' => 'L',
            'full_name' => 'L K',
            'email' => 'nv06@test.local',
            'is_active' => true,
        ]);

        $group = WorkScheduleGroup::where('company_id', $company->id)->where('group_type', 'non_production')->first();
        $pattern = $group->patterns()->first();

        \App\Models\EmployeeWorkSchedule::create([
            'company_id' => $company->id,
            'employee_id' => $emp->id,
            'work_schedule_group_id' => $group->id,
            'work_schedule_pattern_id' => $pattern->id,
            'effective_from' => '2026-06-01',
            'effective_to' => '2026-06-15',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->post('/api/v1/work-schedules/assignments/bulk', [
            'employee_ids' => [$emp->id],
            'shift_id' => $pattern->id,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-20',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.assigned', 0);
        $response->assertJsonPath('data.skipped', 1);
    }

    public function test_can_store_week_override(): void
    {
        [$user, $company] = $this->adminUser();

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV003',
            'first_name' => 'E',
            'last_name' => 'F',
            'full_name' => 'F E',
            'email' => 'nv003@test.local',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->post('/api/v1/work-schedules/week-overrides', [
            'employee_id' => $employee->id,
            'week_start' => '2026-05-05',
            'swap_enabled' => true,
            'swap_rest_day' => 6,
            'swap_work_day' => 7,
            'notes' => 'Tuần lễ',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('work_schedule_week_overrides', [
            'employee_id' => $employee->id,
            'swap_rest_day' => 6,
            'swap_work_day' => 7,
        ]);
    }
}
