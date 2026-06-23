<?php

namespace Tests\Feature;

use App\Models\ContractType;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractTypeTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(Tenant $tenant = null): User
    {
        Role::firstOrCreate(['name' => 'admin']);

        $user = User::factory()->create([
            'email' => 'admin_' . uniqid() . '@example.com',
            'password' => Hash::make('Admin@123'),
            'tenant_id' => $tenant ? $tenant->id : null,
        ]);

        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'admin-token-' . uniqid()])->save();

        return $user;
    }

    public function test_admin_can_perform_contract_type_crud(): void
    {
        $user = $this->createAdminUser();
        $headers = ['Authorization' => 'Bearer ' . $user->api_token];

        // 1. Test POST /api/v1/contract-types (create new type)
        $createData = [
            'code' => 'custom-contract',
            'name' => 'Hợp đồng lao động đặc biệt',
            'is_social_insurance' => true,
            'is_probation' => false,
            'default_duration_months' => 24,
            'is_active' => true,
        ];

        $createResponse = $this->withHeaders($headers)->postJson('/api/v1/contract-types', $createData);
        $createResponse->assertCreated();
        $createResponse->assertJsonPath('data.code', 'custom-contract');
        $createResponse->assertJsonPath('data.name', 'Hợp đồng lao động đặc biệt');
        $createResponse->assertJsonPath('data.is_social_insurance', true);
        $createResponse->assertJsonPath('data.is_probation', false);
        $createResponse->assertJsonPath('data.default_duration_months', 24);
        $createResponse->assertJsonPath('data.is_active', true);

        $contractTypeId = $createResponse->json('data.id');
        $this->assertDatabaseHas('contract_types', ['id' => $contractTypeId, 'code' => 'custom-contract']);

        // 2. Test GET /api/v1/contract-types (list all active and inactive)
        $indexResponse = $this->withHeaders($headers)->getJson('/api/v1/contract-types');
        $indexResponse->assertOk();
        // Default seeds (5) + newly created (1) = 6
        $indexResponse->assertJsonCount(6, 'data');

        // 3. Test GET /api/v1/contract-types/{id} (show details)
        $showResponse = $this->withHeaders($headers)->getJson("/api/v1/contract-types/{$contractTypeId}");
        $showResponse->assertOk();
        $showResponse->assertJsonPath('data.code', 'custom-contract');

        // 4. Test PUT /api/v1/contract-types/{id} (update config)
        $updateData = [
            'code' => 'custom-contract-updated',
            'name' => 'Hợp đồng đặc biệt cải tiến',
            'is_social_insurance' => false,
            'is_probation' => true,
            'default_duration_months' => 3,
            'is_active' => false,
        ];

        $updateResponse = $this->withHeaders($headers)->putJson("/api/v1/contract-types/{$contractTypeId}", $updateData);
        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('data.code', 'custom-contract-updated');
        $updateResponse->assertJsonPath('data.is_social_insurance', false);
        $updateResponse->assertJsonPath('data.is_probation', true);
        $updateResponse->assertJsonPath('data.is_active', false);

        // 5. Test DELETE /api/v1/contract-types/{id} (destroy)
        $deleteResponse = $this->withHeaders($headers)->deleteJson("/api/v1/contract-types/{$contractTypeId}");
        $deleteResponse->assertNoContent();

        $this->assertDatabaseMissing('contract_types', ['id' => $contractTypeId]);
    }

    public function test_hr_meta_api_returns_dynamic_contract_types(): void
    {
        $user = $this->createAdminUser();
        $headers = ['Authorization' => 'Bearer ' . $user->api_token];

        // Initial hr-meta call should contain default contract types
        $initialMeta = $this->withHeaders($headers)->getJson('/api/v1/hr-meta');
        $initialMeta->assertOk();
        $initialMeta->assertJsonPath('data.contract_types.definite', 'Xác định thời hạn');

        // Create a new dynamic contract type
        $newContractType = [
            'code' => 'ct-new-test',
            'name' => 'Hợp đồng Thử nghiệm Mới',
            'is_social_insurance' => false,
            'is_probation' => false,
            'default_duration_months' => 1,
            'is_active' => true,
        ];
        $this->withHeaders($headers)->postJson('/api/v1/contract-types', $newContractType)->assertCreated();

        // Calling hr-meta should now dynamically return the new contract type
        $newMeta = $this->withHeaders($headers)->getJson('/api/v1/hr-meta');
        $newMeta->assertOk();
        $newMeta->assertJsonPath('data.contract_types.ct-new-test', 'Hợp đồng Thử nghiệm Mới');

        // Deactivating the contract type should hide it from hr-meta list
        $activeType = ContractType::where('code', 'ct-new-test')->first();
        $this->withHeaders($headers)->putJson("/api/v1/contract-types/{$activeType->id}", array_merge($newContractType, ['is_active' => false]))->assertOk();

        $inactiveMeta = $this->withHeaders($headers)->getJson('/api/v1/hr-meta');
        $inactiveMeta->assertOk();
        $this->assertNull($inactiveMeta->json('data.contract_types.ct-new-test'));
    }

    public function test_contract_types_are_scoped_by_tenant(): void
    {
        $tenant1 = Tenant::create(['code' => 'TENANT1', 'name' => 'Tenant 1', 'is_active' => true]);
        $tenant2 = Tenant::create(['code' => 'TENANT2', 'name' => 'Tenant 2', 'is_active' => true]);

        $admin1 = $this->createAdminUser($tenant1);
        $admin2 = $this->createAdminUser($tenant2);

        // 1. Admin 1 creates a contract type
        $response1 = $this->withHeaders(['Authorization' => 'Bearer ' . $admin1->api_token])
            ->postJson('/api/v1/contract-types', [
                'code' => 't1-exclusive',
                'name' => 'HĐ Độc quyền T1',
                'is_social_insurance' => true,
                'is_probation' => false,
                'default_duration_months' => 12,
                'is_active' => true,
            ]);
        $response1->assertCreated();

        // 2. Admin 2 creates a contract type with the same code or different
        $response2 = $this->withHeaders(['Authorization' => 'Bearer ' . $admin2->api_token])
            ->postJson('/api/v1/contract-types', [
                'code' => 't2-exclusive',
                'name' => 'HĐ Độc quyền T2',
                'is_social_insurance' => false,
                'is_probation' => true,
                'default_duration_months' => 1,
                'is_active' => true,
            ]);
        $response2->assertCreated();

        // 3. Admin 1 indexes contract types - should NOT see Tenant 2's custom type
        $index1 = $this->withHeaders(['Authorization' => 'Bearer ' . $admin1->api_token])
            ->getJson('/api/v1/contract-types');
        $index1->assertOk();
        
        $codes1 = collect($index1->json('data'))->pluck('code')->toArray();
        $this->assertContains('t1-exclusive', $codes1);
        $this->assertNotContains('t2-exclusive', $codes1);

        // 4. Admin 2 indexes contract types - should NOT see Tenant 1's custom type
        $index2 = $this->withHeaders(['Authorization' => 'Bearer ' . $admin2->api_token])
            ->getJson('/api/v1/contract-types');
        $index2->assertOk();

        $codes2 = collect($index2->json('data'))->pluck('code')->toArray();
        $this->assertContains('t2-exclusive', $codes2);
        $this->assertNotContains('t1-exclusive', $codes2);
    }
}
