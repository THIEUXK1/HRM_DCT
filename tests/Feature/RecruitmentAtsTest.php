<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeOnboardingTask;
use App\Models\Offer;
use App\Models\RecruitmentRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Approval\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecruitmentAtsTest extends TestCase
{
    use RefreshDatabase;

    private function headers(): array
    {
        Role::create(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'tok'])->save();

        return ['Authorization' => 'Bearer tok'];
    }

    public function test_full_ats_flow_hire_with_onboarding(): void
    {
        $headers = $this->headers();
        $this->seed(\Database\Seeders\InitialHrDataSeeder::class);
        $this->seed(\Database\Seeders\HcmPlatformSeeder::class);
        $this->seed(\Database\Seeders\HcmExtendedSeeder::class);

        $tenant = Tenant::first();
        $company = Company::first();
        $company->update(['tax_code' => '0123456789']);

        $reqRes = $this->withHeaders($headers)->postJson('/api/v1/recruitment-requests', [
            'company_id' => $company->id,
            'title' => 'Tuyển dev',
            'headcount' => 2,
            'description' => 'JD nội bộ',
        ])->assertCreated();

        $requestId = $reqRes->json('data.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/recruitment-requests/{$requestId}/submit")
            ->assertOk();

        $instance = ApprovalInstance::where('entity_type', 'recruitment_request')
            ->where('entity_id', $requestId)
            ->first();

        $this->assertNotNull($instance);

        app(ApprovalService::class)->approve($instance, User::first()->id);
        app(ApprovalService::class)->approve($instance->fresh(), User::first()->id);

        $this->assertSame('approved', RecruitmentRequest::find($requestId)->status);

        $jobRes = $this->withHeaders($headers)->postJson('/api/v1/job-posts', [
            'recruitment_request_id' => $requestId,
            'title' => 'Tin tuyển Dev',
            'job_description' => 'Mô tả JD',
            'channel' => 'LinkedIn',
        ])->assertCreated();

        $candidate = Candidate::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'job_post_id' => $jobRes->json('data.id'),
            'full_name' => 'Nguyen Van Test',
            'email' => 'uv@test.local',
            'stage' => 'offer',
        ]);

        $offer = Offer::create([
            'candidate_id' => $candidate->id,
            'salary_base' => 15_000_000,
            'start_date' => now()->addWeek(),
            'contract_type' => 'probation',
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $hireRes = $this->withHeaders($headers)
            ->postJson("/api/v1/candidates/{$candidate->id}/hire", [])
            ->assertCreated();

        $employeeId = $hireRes->json('data.id');
        $this->assertNotNull(Employee::find($employeeId));
        $this->assertTrue(EmployeeOnboardingTask::where('employee_id', $employeeId)->exists());
        $this->assertSame('hired', $candidate->fresh()->stage);
        $this->assertSame($employeeId, $candidate->fresh()->employee_id);
    }

    public function test_public_career_apply_creates_candidate(): void
    {
        $this->seed(\Database\Seeders\InitialHrDataSeeder::class);
        $this->seed(\Database\Seeders\HcmPlatformSeeder::class);
        $this->seed(\Database\Seeders\HcmExtendedSeeder::class);

        $tenant = Tenant::first();
        $company = Company::first();
        $request = RecruitmentRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'code' => 'REQ-PUB',
            'title' => 'Public job',
            'headcount' => 1,
            'status' => 'approved',
        ]);

        $job = \App\Models\JobPost::create([
            'recruitment_request_id' => $request->id,
            'title' => 'Dev public',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->postJson("/api/v1/public/job-posts/{$job->id}/apply", [
            'full_name' => 'Public User',
            'email' => 'pub@test.local',
            'experience_summary' => '3 years',
        ])->assertCreated();

        $this->assertDatabaseHas('candidates', [
            'full_name' => 'Public User',
            'source' => 'career_portal',
            'stage' => 'applied',
        ]);
    }

    public function test_hire_requires_accepted_offer(): void
    {
        $headers = $this->headers();
        $tenant = Tenant::create(['code' => 'T2', 'name' => 'T2', 'is_active' => true]);
        $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'B', 'code' => 'B', 'is_active' => true]);

        $candidate = Candidate::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'full_name' => 'No Offer',
            'stage' => 'offer',
        ]);

        Offer::create([
            'candidate_id' => $candidate->id,
            'salary_base' => 10_000_000,
            'start_date' => now(),
            'status' => 'pending',
        ]);

        $this->withHeaders($headers)
            ->postJson("/api/v1/candidates/{$candidate->id}/hire", [])
            ->assertStatus(422);
    }
}
