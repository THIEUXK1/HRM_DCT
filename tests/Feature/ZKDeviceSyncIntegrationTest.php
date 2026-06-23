<?php

namespace Tests\Feature;

use App\Jobs\SyncZkTecoEmployeesJob;
use App\Models\AttendanceDevice;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Models\ZkTecoSyncBatch;
use App\Models\ZkTecoSyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ZKDeviceSyncIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function setupAdminUser(): array
    {
        Role::firstOrCreate(['name' => 'admin']);
        Permission::findOrCreate('attendance.manage', 'web');

        $tenant = Tenant::create(['code' => 'T_SYNC', 'name' => 'Tenant Sync']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C_SYNC', 'name' => 'Company Sync']);

        $branch = \App\Models\Branch::create([
            'company_id' => $company->id,
            'code' => 'B_SYNC',
            'name' => 'Branch Sync',
        ]);

        $department = Department::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'code' => 'D_SYNC',
            'name' => 'Dept Sync',
        ]);

        $employee1 = Employee::create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'employee_code' => 'NV-SYNC-1',
            'first_name' => 'Tuan',
            'last_name' => 'Nguyen',
            'full_name' => 'Nguyen Tuan',
            'email' => 'tuan@test.local',
            'is_active' => true,
        ]);
        $employee1->profile()->create([
            'biometric_id' => '2002',
            'card_number' => '11223344',
        ]);

        // Employee 2: missing biometric_id
        $employee2 = Employee::create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'employee_code' => 'NV-SYNC-2',
            'first_name' => 'Hoang',
            'last_name' => 'Tran',
            'full_name' => 'Tran Hoang',
            'email' => 'hoang@test.local',
            'is_active' => true,
        ]);
        $employee2->profile()->create([
            'card_number' => '55667788',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee1->id,
            'default_company_id' => $company->id,
        ]);
        $user->assignRole('admin');
        $user->givePermissionTo('attendance.manage');
        $user->forceFill(['api_token' => 'admin-sync-tok'])->save();

        $device = AttendanceDevice::create([
            'company_id' => $company->id,
            'name' => 'Device Test 1',
            'code' => 'DEV_T1',
            'device_type' => 'zkteco',
            'ip_address' => '127.0.0.1', // mock IP
            'port' => 4370,
            'is_active' => true,
        ]);

        return [$user, $company, $device, $department, $employee1, $employee2];
    }

    public function test_fetch_device_info(): void
    {
        [$user, $company, $device] = $this->setupAdminUser();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson("/api/v1/attendance-devices/{$device->id}/fetch-info");

        $response->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.serial_number', 'MOCK-SN-12345678');

        $this->assertEquals('MOCK-SN-12345678', $device->fresh()->serial_number);
    }

    public function test_sync_dry_run_endpoint(): void
    {
        [$user, $company, $device, $department] = $this->setupAdminUser();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/zkteco/sync/dry-run', [
            'mode' => 'all',
            'device_ids' => [$device->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.total_employees', 2)
            ->assertJsonPath('data.total_devices', 1);

        $data = $response->json('data');
        $this->assertNotEmpty($data['missing_biometric']);
        $this->assertEquals('NV-SYNC-2', $data['missing_biometric'][0]['employee_code']);
    }

    public function test_sync_run_dispatches_job(): void
    {
        Queue::fake();

        [$user, $company, $device] = $this->setupAdminUser();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/zkteco/sync/run', [
            'mode' => 'all',
            'device_ids' => [$device->id],
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'status', 'total_employees']]);

        $batchId = $response->json('data.id');
        $this->assertDatabaseHas('zkteco_sync_batches', [
            'id' => $batchId,
            'status' => 'pending',
        ]);

        Queue::assertPushed(SyncZkTecoEmployeesJob::class, function ($job) use ($batchId) {
            return $job->batchId === $batchId;
        });
    }

    public function test_sync_job_executes_successfully(): void
    {
        Queue::fake();

        [$user, $company, $device, $department, $employee1, $employee2] = $this->setupAdminUser();

        // Explicitly set company context for Eloquent scopes and services
        \App\Support\CompanyContext::set($company->id);
        \App\Support\CompanyContext::setTenant($user->tenant_id);

        // 1. Initialize batch and pre-create logs using the service
        $syncService = app(\App\Services\Attendance\ZKTecoSyncService::class);
        $batch = $syncService->runSync(
            companyId: $company->id,
            deviceIds: [$device->id],
            mode: 'all',
            departmentId: null,
            employeeIds: [],
            filters: [],
            options: ['overwrite_mode' => 'skip'],
            requestedBy: $user->id
        );

        // 2. Dispatch and run the job immediately
        $job = new SyncZkTecoEmployeesJob($batch->id, ['overwrite_mode' => 'skip']);
        $job->handle();

        // 3. Verify database states
        $batch->refresh();
        $this->assertEquals('completed', $batch->status);
        $this->assertEquals(1, $batch->success_count); // NV-SYNC-1 succeeded
        $this->assertEquals(0, $batch->failed_count);
        $this->assertEquals(1, $batch->skipped_count); // NV-SYNC-2 skipped due to missing biometric_id

        $this->assertDatabaseHas('zkteco_sync_logs', [
            'batch_id' => $batch->id,
            'employee_code' => 'NV-SYNC-1',
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('zkteco_sync_logs', [
            'batch_id' => $batch->id,
            'employee_code' => 'NV-SYNC-2',
            'status' => 'skipped',
        ]);
    }
}
