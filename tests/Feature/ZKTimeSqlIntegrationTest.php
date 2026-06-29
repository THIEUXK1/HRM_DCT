<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\AttendancePunch;
use App\Models\AttendanceRawLog;
use App\Models\AttendanceSource;
use App\Models\AttendanceSyncLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeAttendanceMapping;
use App\Models\Tenant;
use App\Models\User;
use App\Models\AttendancePeriodLock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * @group integration
 */
class ZKTimeSqlIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $company;
    private User $admin;
    private Employee $employee1;
    private Employee $employee2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['code' => 'T1', 'name' => 'Tập đoàn']);
        $this->company = Company::create(['tenant_id' => $this->tenant->id, 'code' => 'C1', 'name' => 'Công ty 1']);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'default_company_id' => $this->company->id,
            'password' => Hash::make('Admin@123'),
        ]);
        $this->admin->assignRole('admin');
        $this->admin->forceFill(['api_token' => 'tok-admin'])->save();

        $this->employee1 = Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'NV-001',
            'first_name' => 'An',
            'last_name' => 'Nguyen',
            'full_name' => 'An Nguyen',
            'email' => 'an@test.local',
            'is_active' => true,
        ]);

        $this->employee2 = Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'NV-002',
            'first_name' => 'Binh',
            'last_name' => 'Tran',
            'full_name' => 'Binh Tran',
            'email' => 'binh@test.local',
            'is_active' => true,
        ]);

        $this->createFakeZkTables();
    }

    private function createFakeZkTables(): void
    {
        Schema::dropIfExists('USERINFO');
        Schema::dropIfExists('CHECKINOUT');

        Schema::create('USERINFO', function ($table) {
            $table->integer('USERID')->primary();
            $table->string('SSN');
            $table->string('Badgenumber');
        });

        Schema::create('CHECKINOUT', function ($table) {
            $table->integer('id')->primary();
            $table->integer('USERID');
            $table->dateTime('CHECKTIME');
            $table->string('CHECKTYPE');
            $table->string('SENSORID');
        });
    }

    public function test_connection_testing(): void
    {
        $source = AttendanceSource::create([
            'company_id' => $this->company->id,
            'name' => 'ZKTime Test',
            'host' => '127.0.0.1',
            'port' => 1433,
            'database_name' => 'Zktime',
            'username' => 'sa',
            'password_encrypted' => 'SecretPass',
            'user_table' => 'USERINFO',
            'checkinout_table' => 'CHECKINOUT',
            'employee_code_field' => 'SSN',
            'badge_field' => 'Badgenumber',
            'check_time_field' => 'CHECKTIME',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->postJson("/api/v1/attendance-sources/{$source->id}/test-connection");

        $response->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonStructure(['data' => ['ok', 'message', 'user_count', 'log_count']]);
    }

    public function test_connection_testing_fails_if_table_missing(): void
    {
        Schema::dropIfExists('USERINFO');

        $source = AttendanceSource::create([
            'company_id' => $this->company->id,
            'name' => 'ZKTime Test',
            'host' => '127.0.0.1',
            'port' => 1433,
            'database_name' => 'Zktime',
            'username' => 'sa',
            'password_encrypted' => 'SecretPass',
            'user_table' => 'USERINFO',
            'checkinout_table' => 'CHECKINOUT',
            'employee_code_field' => 'SSN',
            'badge_field' => 'Badgenumber',
            'check_time_field' => 'CHECKTIME',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->postJson("/api/v1/attendance-sources/{$source->id}/test-connection");

        $response->assertOk()
            ->assertJsonPath('data.ok', false);
    }

    public function test_source_crud_operations(): void
    {
        // 1. Create
        $response = $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->postJson('/api/v1/attendance-sources', [
            'company_id' => $this->company->id,
            'name' => 'New ZKTime Source',
            'host' => '10.0.60.33',
            'port' => 1433,
            'database_name' => 'Zktime',
            'username' => 'sa',
            'password_encrypted' => 'my-password',
            'user_table' => 'USERINFO',
            'checkinout_table' => 'CHECKINOUT',
            'employee_code_field' => 'SSN',
            'badge_field' => 'Badgenumber',
            'check_time_field' => 'CHECKTIME',
            'is_active' => true,
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New ZKTime Source');

        $sourceId = $response->json('data.id');

        // 2. Read (list)
        $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->getJson('/api/v1/attendance-sources')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // 3. Update
        $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->putJson("/api/v1/attendance-sources/{$sourceId}", [
            'name' => 'Updated ZKTime Name',
            'host' => '10.0.60.34',
            'database_name' => 'Zktime_New',
            'username' => 'sa',
            'password_encrypted' => '', // Blank should not overwrite existing
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated ZKTime Name');

        // Verify password was not overwritten
        $this->assertEquals('Updated ZKTime Name', AttendanceSource::find($sourceId)->name);
        $this->assertEquals('my-password', AttendanceSource::find($sourceId)->password_encrypted);

        // 4. Destroy
        $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->deleteJson("/api/v1/attendance-sources/{$sourceId}")
            ->assertStatus(204);

        $this->assertNull(AttendanceSource::find($sourceId));
    }

    public function test_sync_dry_run_and_actual_run(): void
    {
        // Seed ZKTime tables
        DB::table('USERINFO')->insert([
            ['USERID' => 10, 'SSN' => 'NV-001', 'Badgenumber' => '1001'],
            ['USERID' => 20, 'SSN' => 'NV-002', 'Badgenumber' => '1002'],
        ]);

        DB::table('CHECKINOUT')->insert([
            ['USERID' => 10, 'CHECKTIME' => '2026-06-08 08:00:00', 'CHECKTYPE' => 'I', 'SENSORID' => '1'],
            ['USERID' => 10, 'CHECKTIME' => '2026-06-08 17:30:00', 'CHECKTYPE' => 'O', 'SENSORID' => '1'],
            ['USERID' => 20, 'CHECKTIME' => '2026-06-08 08:15:00', 'CHECKTYPE' => 'I', 'SENSORID' => '2'],
        ]);

        $source = AttendanceSource::create([
            'company_id' => $this->company->id,
            'name' => 'ZKTime Sync',
            'host' => '127.0.0.1',
            'port' => 1433,
            'database_name' => 'Zktime',
            'username' => 'sa',
            'password_encrypted' => 'SecretPass',
            'user_table' => 'USERINFO',
            'checkinout_table' => 'CHECKINOUT',
            'employee_code_field' => 'SSN',
            'badge_field' => 'Badgenumber',
            'check_time_field' => 'CHECKTIME',
            'is_active' => true,
        ]);

        // 1. Dry Run
        $response = $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->postJson("/api/v1/attendance-sources/{$source->id}/sync", [
            'from' => '2026-06-08',
            'to' => '2026-06-08',
            'dry_run' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.dry_run', true)
            ->assertJsonPath('data.total_read', 3)
            ->assertJsonPath('data.new_logs', 3)
            ->assertJsonPath('data.duplicates', 0)
            ->assertJsonPath('data.unmapped', 0);

        // Verify no DB modifications
        $this->assertEquals(0, AttendanceRawLog::count());
        $this->assertEquals(0, AttendancePunch::count());

        // 2. Real Run
        $response = $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->postJson("/api/v1/attendance-sources/{$source->id}/sync", [
            'from' => '2026-06-08',
            'to' => '2026-06-08',
            'dry_run' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.dry_run', false)
            ->assertJsonPath('data.total_read', 3)
            ->assertJsonPath('data.inserted', 3)
            ->assertJsonPath('data.skipped', 0)
            ->assertJsonPath('data.unmapped', 0);

        // Verify DB writes
        $this->assertEquals(3, AttendanceRawLog::count());
        $this->assertEquals(3, AttendancePunch::count());

        // Verify auto-mapping was created
        $this->assertDatabaseHas('employee_attendance_mappings', [
            'company_id' => $this->company->id,
            'employee_id' => $this->employee1->id,
            'device_user_id' => '10',
            'employee_code' => 'NV-001',
        ]);

        // Verify daily logs
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee1->id,
            'work_date' => '2026-06-08 00:00:00',
            'check_in_at' => Carbon::parse('2026-06-08 08:00:00', 'Asia/Ho_Chi_Minh')->timezone(config('app.timezone'))->toDateTimeString(),
            'check_out_at' => Carbon::parse('2026-06-08 17:30:00', 'Asia/Ho_Chi_Minh')->timezone(config('app.timezone'))->toDateTimeString(),
        ]);
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee2->id,
            'work_date' => '2026-06-08 00:00:00',
            'check_in_at' => Carbon::parse('2026-06-08 08:15:00', 'Asia/Ho_Chi_Minh')->timezone(config('app.timezone'))->toDateTimeString(),
            'check_out_at' => null, // Only 1 punch
        ]);

        // 3. Second Run (should skip duplicates)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->postJson("/api/v1/attendance-sources/{$source->id}/sync", [
            'from' => '2026-06-08',
            'to' => '2026-06-08',
            'dry_run' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.total_read', 3)
            ->assertJsonPath('data.inserted', 0)
            ->assertJsonPath('data.skipped', 3);
    }

    public function test_sync_unmapped_and_saving_mapping(): void
    {
        // Seed ZKTime tables with an employee code not in our HRM database
        DB::table('USERINFO')->insert([
            ['USERID' => 99, 'SSN' => 'NV-UNKNOWN', 'Badgenumber' => '9999'],
        ]);

        DB::table('CHECKINOUT')->insert([
            ['USERID' => 99, 'CHECKTIME' => '2026-06-08 08:00:00', 'CHECKTYPE' => 'I', 'SENSORID' => '1'],
        ]);

        $source = AttendanceSource::create([
            'company_id' => $this->company->id,
            'name' => 'ZKTime Unmapped',
            'host' => '127.0.0.1',
            'port' => 1433,
            'database_name' => 'Zktime',
            'username' => 'sa',
            'password_encrypted' => 'SecretPass',
            'user_table' => 'USERINFO',
            'checkinout_table' => 'CHECKINOUT',
            'employee_code_field' => 'SSN',
            'badge_field' => 'Badgenumber',
            'check_time_field' => 'CHECKTIME',
            'is_active' => true,
        ]);

        // Sync
        $response = $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->postJson("/api/v1/attendance-sources/{$source->id}/sync", [
            'from' => '2026-06-08',
            'to' => '2026-06-08',
            'dry_run' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.inserted', 0)
            ->assertJsonPath('data.unmapped', 1);

        // Verify raw log is unmapped
        $this->assertDatabaseHas('attendance_raw_logs', [
            'device_user_id' => '99',
            'status' => 'unmapped',
            'employee_id' => null,
        ]);

        // Get unmapped logs via API
        $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->getJson("/api/v1/attendance-sources/{$source->id}/unmapped-logs")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.device_user_id', '99');

        // Resolve mapping
        $response = $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->postJson('/api/v1/attendance-sources/mappings', [
            'company_id' => $this->company->id,
            'employee_id' => $this->employee1->id,
            'employee_code' => 'NV-001',
            'device_user_id' => '99',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.resolved_logs', 1);

        // Verify status changed to processed
        $this->assertDatabaseHas('attendance_raw_logs', [
            'device_user_id' => '99',
            'status' => 'processed',
            'employee_id' => $this->employee1->id,
        ]);

        // Verify punch is created
        $this->assertDatabaseHas('attendance_punches', [
            'employee_id' => $this->employee1->id,
            'punched_at' => Carbon::parse('2026-06-08 08:00:00', 'Asia/Ho_Chi_Minh')->timezone(config('app.timezone'))->toDateTimeString(),
        ]);
    }

    public function test_sync_fails_when_period_is_locked(): void
    {
        // Lock month of June 2026
        AttendancePeriodLock::create([
            'company_id' => $this->company->id,
            'period' => '2026-06',
            'locked_at' => now(),
        ]);

        $source = AttendanceSource::create([
            'company_id' => $this->company->id,
            'name' => 'ZKTime Locked',
            'host' => '127.0.0.1',
            'port' => 1433,
            'database_name' => 'Zktime',
            'username' => 'sa',
            'password_encrypted' => 'SecretPass',
            'user_table' => 'USERINFO',
            'checkinout_table' => 'CHECKINOUT',
            'employee_code_field' => 'SSN',
            'badge_field' => 'Badgenumber',
            'check_time_field' => 'CHECKTIME',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->postJson("/api/v1/attendance-sources/{$source->id}/sync", [
            'from' => '2026-06-08',
            'to' => '2026-06-08',
            'dry_run' => false,
        ]);

        $response->assertStatus(500); // bubbled exception as 500 internal error
    }

    public function test_cli_commands(): void
    {
        DB::table('USERINFO')->insert([
            ['USERID' => 10, 'SSN' => 'NV-001', 'Badgenumber' => '1001'],
        ]);

        DB::table('CHECKINOUT')->insert([
            ['USERID' => 10, 'CHECKTIME' => '2026-06-08 08:00:00', 'CHECKTYPE' => 'I', 'SENSORID' => '1'],
        ]);

        $source = AttendanceSource::create([
            'company_id' => $this->company->id,
            'name' => 'ZKTime CLI',
            'host' => '127.0.0.1',
            'port' => 1433,
            'database_name' => 'Zktime',
            'username' => 'sa',
            'password_encrypted' => 'SecretPass',
            'user_table' => 'USERINFO',
            'checkinout_table' => 'CHECKINOUT',
            'employee_code_field' => 'SSN',
            'badge_field' => 'Badgenumber',
            'check_time_field' => 'CHECKTIME',
            'is_active' => true,
        ]);

        // Run Artisan command (dry-run)
        $this->artisan('attendance:sync-zktime', [
            '--source-id' => $source->id,
            '--from' => '2026-06-08',
            '--to' => '2026-06-08',
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertEquals(0, AttendanceRawLog::count());

        // Run Artisan command (real run)
        $this->artisan('attendance:sync-zktime', [
            '--source-id' => $source->id,
            '--from' => '2026-06-08',
            '--to' => '2026-06-08',
        ])->assertExitCode(0);

        $this->assertEquals(1, AttendanceRawLog::count());

        // Run Scheduled command (which automatically processes active sources for yesterday and today)
        // Let's modify CHECKTIME in db to be yesterday
        DB::table('CHECKINOUT')->update(['CHECKTIME' => Carbon::yesterday()->hour(8)->minute(0)->toDateTimeString()]);
        
        // Clear previous raw logs to allow sync
        AttendanceRawLog::truncate();
        AttendancePunch::truncate();

        $this->artisan('attendance:sync-zktime-scheduled')
            ->assertExitCode(0);

        $this->assertEquals(1, AttendanceRawLog::count());
    }

    public function test_sync_badge_numbers_dry_run_and_actual(): void
    {
        // 1. Setup ZKTime users
        DB::table('USERINFO')->insert([
            ['USERID' => 10, 'SSN' => 'NV-001', 'Badgenumber' => 'NV-001'],
            ['USERID' => 20, 'SSN' => 'NV-002', 'Badgenumber' => 'NV-002'],
        ]);

        $source = AttendanceSource::create([
            'company_id' => $this->company->id,
            'name' => 'ZKTime Badge Sync Test',
            'host' => '127.0.0.1',
            'port' => 1433,
            'database_name' => 'Zktime',
            'username' => 'sa',
            'password_encrypted' => 'SecretPass',
            'user_table' => 'USERINFO',
            'checkinout_table' => 'CHECKINOUT',
            'employee_code_field' => 'SSN',
            'badge_field' => 'Badgenumber',
            'check_time_field' => 'CHECKTIME',
            'is_active' => true,
        ]);

        // Dry Run
        $response = $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->postJson("/api/v1/attendance-sources/{$source->id}/sync-badge-numbers", [
            'dry_run' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.dry_run', true)
            ->assertJsonPath('data.total_read', 2)
            ->assertJsonPath('data.matched_count', 2)
            ->assertJsonPath('data.updated_count', 2)
            ->assertJsonPath('data.skipped_count', 0);

        // Verify no profile modifications
        $this->assertNull($this->employee1->fresh()->profile?->biometric_id);

        // Real Run
        $response = $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->postJson("/api/v1/attendance-sources/{$source->id}/sync-badge-numbers", [
            'dry_run' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.dry_run', false)
            ->assertJsonPath('data.updated_count', 2);

        // Verify DB writes
        $this->assertEquals('NV-001', $this->employee1->fresh()->profile->biometric_id);
        $this->assertEquals('NV-002', $this->employee2->fresh()->profile->biometric_id);

        // Test second run without force, already has biometric_id
        $response = $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->postJson("/api/v1/attendance-sources/{$source->id}/sync-badge-numbers", [
            'dry_run' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.updated_count', 0)
            ->assertJsonPath('data.skipped_count', 2);
    }

    public function test_sync_badge_numbers_force_overwrite(): void
    {
        // Setup existing profile with a different biometric_id
        $profile = $this->employee1->profile ?: new \App\Models\EmployeeProfile();
        $profile->employee_id = $this->employee1->id;
        $profile->biometric_id = 'OLD-FINGERPRINT';
        $profile->save();

        DB::table('USERINFO')->insert([
            ['USERID' => 10, 'SSN' => 'NV-001', 'Badgenumber' => 'NV-001'],
        ]);

        $source = AttendanceSource::create([
            'company_id' => $this->company->id,
            'name' => 'ZKTime Badge Sync Test',
            'host' => '127.0.0.1',
            'port' => 1433,
            'database_name' => 'Zktime',
            'username' => 'sa',
            'password_encrypted' => 'SecretPass',
            'user_table' => 'USERINFO',
            'checkinout_table' => 'CHECKINOUT',
            'employee_code_field' => 'SSN',
            'badge_field' => 'Badgenumber',
            'check_time_field' => 'CHECKTIME',
            'is_active' => true,
        ]);

        // Run without force (should skip and warn)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->postJson("/api/v1/attendance-sources/{$source->id}/sync-badge-numbers", [
            'dry_run' => false,
            'force' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.updated_count', 0)
            ->assertJsonPath('data.skipped_count', 1);

        $this->assertCount(1, $response->json('data.warnings'));
        $this->assertEquals('OLD-FINGERPRINT', $this->employee1->fresh()->profile->biometric_id);

        // Run with force (should update)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->postJson("/api/v1/attendance-sources/{$source->id}/sync-badge-numbers", [
            'dry_run' => false,
            'force' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.updated_count', 1);

        $this->assertEquals('NV-001', $this->employee1->fresh()->profile->biometric_id);
    }

    public function test_sync_badge_numbers_duplicate_warning(): void
    {
        // Setup ZKTime users where Badgenumber is duplicated in ZKTime DB
        DB::table('USERINFO')->insert([
            ['USERID' => 10, 'SSN' => 'NV-001', 'Badgenumber' => 'NV-DUPLICATE'],
            ['USERID' => 20, 'SSN' => 'NV-002', 'Badgenumber' => 'NV-DUPLICATE'],
        ]);

        $source = AttendanceSource::create([
            'company_id' => $this->company->id,
            'name' => 'ZKTime Badge Sync Test',
            'host' => '127.0.0.1',
            'port' => 1433,
            'database_name' => 'Zktime',
            'username' => 'sa',
            'password_encrypted' => 'SecretPass',
            'user_table' => 'USERINFO',
            'checkinout_table' => 'CHECKINOUT',
            'employee_code_field' => 'SSN',
            'badge_field' => 'Badgenumber',
            'check_time_field' => 'CHECKTIME',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer tok-admin',
            'X-Company-Id' => $this->company->id,
        ])->postJson("/api/v1/attendance-sources/{$source->id}/sync-badge-numbers", [
            'dry_run' => true,
        ]);

        $response->assertOk();
        
        // Find warning about duplication
        $warnings = $response->json('data.warnings');
        $hasDuplicateWarning = false;
        foreach ($warnings as $warn) {
            if (str_contains($warn, "trùng lặp")) {
                $hasDuplicateWarning = true;
                break;
            }
        }
        $this->assertTrue($hasDuplicateWarning, "Should report duplicate badge warning.");
    }

    public function test_sync_badge_numbers_cli_commands(): void
    {
        DB::table('USERINFO')->insert([
            ['USERID' => 10, 'SSN' => 'NV-001', 'Badgenumber' => 'NV-001'],
        ]);

        $source = AttendanceSource::create([
            'company_id' => $this->company->id,
            'name' => 'ZKTime Badge Sync Test',
            'host' => '127.0.0.1',
            'port' => 1433,
            'database_name' => 'Zktime',
            'username' => 'sa',
            'password_encrypted' => 'SecretPass',
            'user_table' => 'USERINFO',
            'checkinout_table' => 'CHECKINOUT',
            'employee_code_field' => 'SSN',
            'badge_field' => 'Badgenumber',
            'check_time_field' => 'CHECKTIME',
            'is_active' => true,
        ]);

        // Run dry-run via command line
        $this->artisan('zktime:sync-badge-number', [
            '--source-id' => $source->id,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertNull($this->employee1->fresh()->profile?->biometric_id);

        // Run actual sync via command line
        $this->artisan('zktime:sync-badge-number', [
            '--source-id' => $source->id,
        ])->assertExitCode(0);

        $this->assertEquals('NV-001', $this->employee1->fresh()->profile->biometric_id);
    }
}
