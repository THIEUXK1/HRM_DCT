<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyResourceTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        Role::create(['name' => 'admin']);

        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('Admin@123'),
        ]);

        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'admin-token'])->save();

        return $user;
    }

    public function test_admin_can_perform_company_crud(): void
    {
        $user = $this->createAdminUser();
        $headers = ['Authorization' => 'Bearer ' . $user->api_token];

        $createData = [
            'name' => 'HRM Global',
            'code' => 'COMP-001',
            'tax_code' => 'HRM2026001',
            'address' => '123 Main Street, Hanoi',
            'phone' => '+84 24 1234 5678',
            'email' => 'contact@hrmglobal.local',
            'is_active' => true,
        ];

        $createResponse = $this->withHeaders($headers)->postJson('/api/v1/companies', $createData);
        $createResponse->assertCreated();
        $createResponse->assertJsonPath('data.name', 'HRM Global');
        $companyId = $createResponse->json('data.id');

        $this->assertDatabaseHas('companies', ['id' => $companyId, 'code' => 'COMP-001']);

        $indexResponse = $this->withHeaders($headers)->getJson('/api/v1/companies');
        $indexResponse->assertOk();
        $indexResponse->assertJsonCount(1, 'data');

        $showResponse = $this->withHeaders($headers)->getJson("/api/v1/companies/{$companyId}");
        $showResponse->assertOk();
        $showResponse->assertJsonPath('data.email', 'contact@hrmglobal.local');

        $updateData = [
            'name' => 'HRM Global Updated',
            'code' => 'COMP-001',
            'tax_code' => 'HRM2026001',
            'address' => '456 New Address, Hanoi',
            'phone' => '+84 24 9999 8888',
            'email' => 'info@hrmglobal.local',
            'is_active' => false,
        ];

        $updateResponse = $this->withHeaders($headers)->putJson("/api/v1/companies/{$companyId}", $updateData);
        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('data.name', 'HRM Global Updated');

        $deleteResponse = $this->withHeaders($headers)->deleteJson("/api/v1/companies/{$companyId}");
        $deleteResponse->assertNoContent();

        $this->withHeaders($headers)->getJson("/api/v1/companies/{$companyId}")
            ->assertNotFound();
    }

    public function test_company_is_scoped_by_tenant(): void
    {
        $tenant1 = \App\Models\Tenant::create(['code' => 'T1', 'name' => 'Tenant One', 'is_active' => true]);
        $tenant2 = \App\Models\Tenant::create(['code' => 'T2', 'name' => 'Tenant Two', 'is_active' => true]);

        Role::firstOrCreate(['name' => 'admin']);

        // Create Admin A in Tenant 1
        $adminA = User::factory()->create([
            'email' => 'adminA@example.com',
            'tenant_id' => $tenant1->id,
            'password' => Hash::make('Admin@123'),
        ]);
        $adminA->assignRole('admin');
        $adminA->forceFill(['api_token' => 'token-A'])->save();

        // Create Admin B in Tenant 2
        $adminB = User::factory()->create([
            'email' => 'adminB@example.com',
            'tenant_id' => $tenant2->id,
            'password' => Hash::make('Admin@123'),
        ]);
        $adminB->assignRole('admin');
        $adminB->forceFill(['api_token' => 'token-B'])->save();

        // Create Company 1 under Tenant 1
        $company1 = Company::create([
            'tenant_id' => $tenant1->id,
            'name' => 'Company A',
            'code' => 'COMPA',
            'is_active' => true,
        ]);

        // Create Company 2 under Tenant 2
        $company2 = Company::create([
            'tenant_id' => $tenant2->id,
            'name' => 'Company B',
            'code' => 'COMPB',
            'is_active' => true,
        ]);

        // Query with Admin A
        $responseA = $this->withHeaders(['Authorization' => 'Bearer token-A'])
            ->getJson('/api/v1/companies');
        
        $responseA->assertOk();
        $responseA->assertJsonCount(1, 'data');
        $responseA->assertJsonPath('data.0.id', $company1->id);

        // Query with Admin B
        $responseB = $this->withHeaders(['Authorization' => 'Bearer token-B'])
            ->getJson('/api/v1/companies');
        
        $responseB->assertOk();
        $responseB->assertJsonCount(1, 'data');
        $responseB->assertJsonPath('data.0.id', $company2->id);
    }
}

