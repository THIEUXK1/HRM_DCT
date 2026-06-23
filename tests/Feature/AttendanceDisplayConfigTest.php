<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Attendance\AttendanceDisplayConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceDisplayConfigTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(?Tenant $tenant = null): User
    {
        Role::firstOrCreate(['name' => 'admin']);

        $user = User::factory()->create([
            'email' => 'admin_' . uniqid() . '@example.com',
            'password' => Hash::make('Admin@123'),
            'tenant_id' => $tenant?->id,
        ]);

        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'admin-token-' . uniqid()])->save();

        return $user;
    }

    private function headers(User $user, Company $company): array
    {
        return [
            'Authorization' => 'Bearer ' . $user->api_token,
            'X-Company-Id' => (string) $company->id,
        ];
    }

    public function test_attendance_viewer_can_read_display_config(): void
    {
        $user = $this->createAdminUser();
        $company = Company::create(['name' => 'Test Co', 'code' => 'TC']);
        \App\Support\CompanyContext::set($company->id);

        $response = $this->withHeaders($this->headers($user, $company))
            ->getJson('/api/v1/attendance-display-config');

        $response->assertOk();
        $response->assertJsonPath('data.employment_phases.probation.label', 'Thử việc');
        $response->assertJsonPath('data.cell_statuses.present.bg_color', '#f0fdf4');
    }

    public function test_admin_can_update_display_config(): void
    {
        $user = $this->createAdminUser();
        $company = Company::create(['name' => 'Test Co', 'code' => 'TC2']);
        \App\Support\CompanyContext::set($company->id);

        $payload = [
            'config' => [
                'employment_phases' => [
                    'probation' => [
                        'legend_color_name' => 'tím',
                        'text_color' => '#6d28d9',
                        'bg_color' => '#f5f3ff',
                    ],
                ],
                'cell_statuses' => [
                    'absent' => [
                        'text_color' => '#991b1b',
                    ],
                ],
            ],
        ];

        $response = $this->withHeaders($this->headers($user, $company))
            ->putJson('/api/v1/attendance-display-config', $payload);

        $response->assertOk();
        $response->assertJsonPath('data.employment_phases.probation.legend_color_name', 'tím');
        $response->assertJsonPath('data.employment_phases.probation.text_color', '#6d28d9');

        $stored = CompanySetting::where('company_id', $company->id)
            ->where('key', AttendanceDisplayConfigService::SETTING_KEY)
            ->value('value');

        $this->assertNotNull($stored);
        $this->assertStringContainsString('tím', $stored);
    }

    public function test_timesheet_includes_display_config(): void
    {
        $user = $this->createAdminUser();
        $company = Company::create(['name' => 'Test Co', 'code' => 'TC3']);
        \App\Support\CompanyContext::set($company->id);

        $this->withHeaders($this->headers($user, $company))
            ->putJson('/api/v1/attendance-display-config', [
                'config' => [
                    'employment_phases' => [
                        'official' => [
                            'short_label' => 'CT2',
                        ],
                    ],
                ],
            ])
            ->assertOk();

        $response = $this->withHeaders($this->headers($user, $company))
            ->getJson('/api/v1/attendance-reports/timesheet?period=' . now()->format('Y-m'));

        $response->assertOk();
        $response->assertJsonPath('data.display_config.employment_phases.official.short_label', 'CT2');
    }
}
