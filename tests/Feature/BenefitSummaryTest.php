<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\BenefitSeeder;
use Database\Seeders\InitialHrDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BenefitSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_benefits_summary_returns_ok_for_authenticated_company(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(InitialHrDataSeeder::class);
        $this->seed(BenefitSeeder::class);

        $company = Company::where('code', 'COMP-001')->first();
        $this->assertNotNull($company);

        $user = User::factory()->create([
            'tenant_id' => $company->tenant_id,
            'password' => Hash::make('Admin@123'),
            'default_company_id' => $company->id,
        ]);
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'benefit-summary-tok'])->save();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer benefit-summary-tok',
            'X-Company-Id' => $company->id,
        ])->getJson('/api/v1/benefits/summary');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'total_plans',
                'total_enrolled',
                'total_employees',
                'monthly_cost_est',
                'by_category',
                'plans',
            ],
        ]);
    }
}
