<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveEntitlementGroup;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\CompanyContext;
use Database\Seeders\HcmPlatformSeeder;
use Database\Seeders\InitialHrDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeaveEntitlementTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithCompany(): array
    {
        $this->seed(InitialHrDataSeeder::class);
        $this->seed(HcmPlatformSeeder::class);

        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create([
            'password' => Hash::make('Admin@123'),
        ]);
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'test-token-'.uniqid()])->save();

        $company = Company::first();
        CompanyContext::set($company->id);

        return [$user, $company];
    }

    public function test_default_groups_are_created_and_heavy_labor_entitlement_applies(): void
    {
        [$user, $company] = $this->adminWithCompany();
        $headers = [
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ];

        $groupsRes = $this->getJson('/api/v1/leave-entitlement-groups', $headers);
        $groupsRes->assertOk();
        $groups = $groupsRes->json('data');
        $this->assertGreaterThanOrEqual(2, count($groups));

        $heavy = collect($groups)->firstWhere('code', 'HEAVY_LABOR');
        $this->assertNotNull($heavy);
        $this->assertSame(14, $heavy['annual_days']);

        $employee = Employee::where('company_id', $company->id)->first();
        LeaveEntitlementGroup::where('id', $heavy['id'])->update(['annual_days' => 14]);
        $employee->update(['leave_entitlement_group_id' => $heavy['id']]);

        $balanceRes = $this->getJson("/api/v1/employees/{$employee->id}/leave-balance", $headers);
        $balanceRes->assertOk()
            ->assertJsonPath('data.annual_days', 14)
            ->assertJsonPath('data.source', 'employee_group');
    }

    public function test_individual_override_takes_precedence(): void
    {
        [$user, $company] = $this->adminWithCompany();
        $headers = [
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ];

        $employee = Employee::where('company_id', $company->id)->first();
        $employee->update([
            'annual_leave_days_override' => 16,
            'leave_entitlement_group_id' => null,
        ]);

        $this->getJson("/api/v1/employees/{$employee->id}/leave-balance", $headers)
            ->assertOk()
            ->assertJsonPath('data.annual_days', 16)
            ->assertJsonPath('data.source', 'employee_override');
    }

    public function test_used_annual_leave_is_subtracted_from_balance(): void
    {
        [$user, $company] = $this->adminWithCompany();
        $headers = [
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ];

        $employee = Employee::where('company_id', $company->id)->first();
        $employee->update(['annual_leave_days_override' => 12, 'leave_entitlement_group_id' => null]);

        $leaveType = LeaveType::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'PHEP'],
            ['name' => 'Phép năm', 'is_paid' => true]
        );

        LeaveRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->startOfYear()->addDays(2)->toDateString(),
            'total_days' => 3,
            'status' => 'approved',
        ]);

        $this->getJson("/api/v1/employees/{$employee->id}/leave-balance", $headers)
            ->assertOk()
            ->assertJsonPath('data.used_days', 3)
            ->assertJsonPath('data.remaining_days', 9);
    }
}
