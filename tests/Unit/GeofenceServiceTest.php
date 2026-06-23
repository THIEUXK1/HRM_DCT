<?php

namespace Tests\Unit;

use App\Models\AttendanceGeofenceZone;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Tenant;
use App\Services\Attendance\GeofenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeofenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_point_inside_zone_radius(): void
    {
        $tenant = Tenant::create(['code' => 'T1', 'name' => 'T1']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C1', 'name' => 'C1']);

        $zone = AttendanceGeofenceZone::create([
            'company_id' => $company->id,
            'code' => 'NM-1',
            'name' => 'Nhà máy',
            'zone_type' => 'factory',
            'latitude' => 10.776889,
            'longitude' => 106.700806,
            'radius_meters' => 350,
            'allowed_sources' => ['mobile', 'device'],
            'is_active' => true,
        ]);

        $service = app(GeofenceService::class);

        $this->assertTrue($service->isInsideZone($zone, 10.776900, 106.700820));
        $this->assertFalse($service->isInsideZone($zone, 10.800000, 106.800000));

        $match = $service->bestMatch($company->id, 10.776900, 106.700820, 'mobile');
        $this->assertNotNull($match);
        $this->assertSame($zone->id, $match->id);
    }

    public function test_zones_for_employee_respects_branch(): void
    {
        $tenant = Tenant::create(['code' => 'T1', 'name' => 'T1']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C1', 'name' => 'C1']);
        $branchA = Branch::create(['company_id' => $company->id, 'code' => 'A', 'name' => 'A', 'is_active' => true]);
        $branchB = Branch::create(['company_id' => $company->id, 'code' => 'B', 'name' => 'B', 'is_active' => true]);

        AttendanceGeofenceZone::create([
            'company_id' => $company->id,
            'branch_id' => $branchA->id,
            'code' => 'A-1',
            'name' => 'Zone A',
            'zone_type' => 'office',
            'latitude' => 10.776889,
            'longitude' => 106.700806,
            'radius_meters' => 100,
            'is_active' => true,
        ]);
        AttendanceGeofenceZone::create([
            'company_id' => $company->id,
            'branch_id' => $branchB->id,
            'code' => 'B-1',
            'name' => 'Zone B',
            'zone_type' => 'office',
            'latitude' => 10.782000,
            'longitude' => 106.695000,
            'radius_meters' => 100,
            'is_active' => true,
        ]);
        AttendanceGeofenceZone::create([
            'company_id' => $company->id,
            'code' => 'COMMON',
            'name' => 'Common',
            'zone_type' => 'office',
            'latitude' => 10.780000,
            'longitude' => 106.698000,
            'radius_meters' => 100,
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branchA->id,
            'employee_code' => 'NV-1',
            'first_name' => 'A',
            'last_name' => 'B',
            'full_name' => 'A B',
            'email' => 'ab@test.local',
            'is_active' => true,
        ]);

        $service = app(GeofenceService::class);
        $zones = $service->zonesForEmployee($employee);

        $this->assertCount(2, $zones);
        $this->assertTrue($zones->pluck('code')->contains('A-1'));
        $this->assertTrue($zones->pluck('code')->contains('COMMON'));
        $this->assertFalse($zones->pluck('code')->contains('B-1'));
    }
}
