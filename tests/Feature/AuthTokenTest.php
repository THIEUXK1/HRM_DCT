<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_api_token_and_does_not_expose_api_token_on_user_payload(): void
    {
        $password = 'Admin@123';
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make($password),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['token', 'user']]);
        $this->assertNotNull($response->json('data.token'));
        $this->assertArrayNotHasKey('api_token', $response->json('data.user'));
    }

    public function test_protected_routes_require_a_valid_api_token(): void
    {
        $response = $this->getJson('/api/v1/auth/me');
        $response->assertUnauthorized();
    }

    public function test_me_returns_authenticated_user_with_api_token(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('Password123!'),
        ]);
        $user->forceFill(['api_token' => 'test-token-123'])->save();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $user->api_token,
        ])->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJsonPath('data.email', $user->email);
        $response->assertJsonMissing(['api_token' => 'test-token-123']);
    }

    public function test_rotate_issues_a_new_token_and_revokes_old_token(): void
    {
        $user = User::factory()->create([
            'email' => 'rotate@example.com',
            'password' => Hash::make('Password123!'),
        ]);
        $user->forceFill(['api_token' => 'old-token'])->save();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer old-token',
        ])->postJson('/api/v1/auth/rotate');

        $response->assertOk();
        $newToken = $response->json('data.token');
        $this->assertNotEmpty($newToken);
        $this->assertNotSame('old-token', $newToken);

        $this->withHeaders([
            'Authorization' => 'Bearer old-token',
        ])->withCookie('laravel_session', '')->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $newToken,
        ])->withCookie('laravel_session', '')->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_logout_revokes_api_token(): void
    {
        $user = User::factory()->create([
            'email' => 'logout@example.com',
            'password' => Hash::make('Password123!'),
        ]);
        $user->forceFill(['api_token' => 'logout-token'])->save();

        $this->withHeaders(['Authorization' => 'Bearer logout-token'])
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->withHeaders(['Authorization' => 'Bearer logout-token'])
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_employee_unauthorized_for_payroll_and_bhxh(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'employee']);
        
        $user = User::factory()->create([
            'email' => 'emp@example.com',
            'password' => Hash::make('Password123!'),
        ]);
        $user->assignRole('employee');
        $user->forceFill(['api_token' => 'emp-token'])->save();

        // 1. Try to access payroll cycles
        $this->withHeaders(['Authorization' => 'Bearer emp-token'])
            ->getJson('/api/v1/payroll-cycles')
            ->assertForbidden();

        // 2. Try to access bhxh preview
        $this->withHeaders(['Authorization' => 'Bearer emp-token'])
            ->getJson('/api/v1/bhxh/preview')
            ->assertForbidden();
    }
}

