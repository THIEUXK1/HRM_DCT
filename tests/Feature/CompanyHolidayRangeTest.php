<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyHoliday;
use App\Models\User;
use App\Services\Attendance\VietnamHolidayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyHolidayRangeTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['api_token' => 'hol-'.uniqid()]);
        $user->assignRole('admin');
        $company = Company::create(['name' => 'Hol Co', 'code' => 'HOL']);
        \App\Support\CompanyContext::set($company->id);

        return [
            'headers' => [
                'Authorization' => 'Bearer '.$user->api_token,
                'X-Company-Id' => (string) $company->id,
            ],
            'company' => $company,
        ];
    }

    public function test_can_create_multi_day_holiday_range(): void
    {
        $ctx = $this->adminHeaders();

        $response = $this->withHeaders($ctx['headers'])->postJson('/api/v1/company-holidays', [
            'name' => 'Tết Nguyên Đán 2026',
            'holiday_date' => '2026-02-16',
            'end_date' => '2026-02-20',
            'is_paid' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.holiday.day_count', 5);

        $holiday = CompanyHoliday::first();
        $this->assertSame('2026-02-20', $holiday->end_date->format('Y-m-d'));
        $this->assertCount(5, $holiday->expandToDateMap());
    }

    public function test_company_holiday_range_merges_into_calendar(): void
    {
        $company = Company::create(['name' => 'Merge Co', 'code' => 'MC']);
        CompanyHoliday::create([
            'company_id' => $company->id,
            'name' => 'Tết công ty',
            'holiday_date' => '2026-02-16',
            'end_date' => '2026-02-18',
            'is_paid' => true,
        ]);

        $map = VietnamHolidayService::forYear(2026, $company->id);

        $this->assertArrayHasKey('2026-02-16', $map);
        $this->assertArrayHasKey('2026-02-17', $map);
        $this->assertArrayHasKey('2026-02-18', $map);
        $this->assertSame('Tết công ty', $map['2026-02-16']);
    }

    public function test_rejects_overlapping_holiday_ranges(): void
    {
        $ctx = $this->adminHeaders();

        CompanyHoliday::create([
            'company_id' => $ctx['company']->id,
            'name' => 'Lễ có sẵn',
            'holiday_date' => '2026-05-01',
            'end_date' => '2026-05-03',
            'is_paid' => true,
        ]);

        $this->withHeaders($ctx['headers'])->postJson('/api/v1/company-holidays', [
            'name' => 'Trùng lịch',
            'holiday_date' => '2026-05-02',
            'end_date' => '2026-05-04',
            'is_paid' => true,
        ])->assertStatus(422);
    }
}
