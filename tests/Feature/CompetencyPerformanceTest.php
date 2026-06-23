<?php

namespace Tests\Feature;

use App\Models\Competency;
use App\Models\CompetencyGroup;
use App\Models\Employee;
use App\Models\EmployeeCompetencyAssessment;
use App\Models\EmployeeReview;
use App\Models\Goal;
use App\Models\PerformanceCycle;
use App\Models\Position;
use App\Models\PositionCompetencyRequirement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompetencyPerformanceTest extends TestCase
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

    public function test_competency_matrix_shows_gap(): void
    {
        $headers = $this->headers();
        $this->seed(\Database\Seeders\InitialHrDataSeeder::class);
        $this->seed(\Database\Seeders\HcmPlatformSeeder::class);
        $this->seed(\Database\Seeders\HcmExtendedSeeder::class);

        $employee = Employee::first();
        $competency = Competency::first();
        $position = $employee->position_id
            ? Position::find($employee->position_id)
            : Position::first();

        if ($position && $competency) {
            PositionCompetencyRequirement::updateOrCreate(
                ['position_id' => $position->id, 'competency_id' => $competency->id],
                ['required_level' => 4]
            );
            $employee->update(['position_id' => $position->id]);
        }

        EmployeeCompetencyAssessment::create([
            'employee_id' => $employee->id,
            'competency_id' => $competency->id,
            'current_level' => 2,
            'assessed_at' => now()->toDateString(),
        ]);

        $res = $this->withHeaders($headers)
            ->getJson("/api/v1/employees/{$employee->id}/competency-matrix")
            ->assertOk();

        $items = collect($res->json('data.items'));
        $row = $items->firstWhere('competency_id', $competency->id);
        $this->assertNotNull($row);
        $this->assertSame('gap', $row['gap_status']);
        $this->assertSame(2, $row['gap']);
    }

    public function test_sync_position_competency_requirements(): void
    {
        $headers = $this->headers();
        $this->seed(\Database\Seeders\InitialHrDataSeeder::class);
        $this->seed(\Database\Seeders\HcmPlatformSeeder::class);
        $this->seed(\Database\Seeders\HcmExtendedSeeder::class);

        $position = Position::first();
        $competency = Competency::first();
        $this->assertNotNull($position);
        $this->assertNotNull($competency);

        $this->withHeaders($headers)
            ->putJson("/api/v1/positions/{$position->id}/competency-requirements", [
                'requirements' => [
                    ['competency_id' => $competency->id, 'required_level' => 5],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('position_competency_requirements', [
            'position_id' => $position->id,
            'competency_id' => $competency->id,
            'required_level' => 5,
        ]);
    }

    public function test_finalize_review_combines_kpi_and_behavior(): void
    {
        $headers = $this->headers();
        $this->seed(\Database\Seeders\InitialHrDataSeeder::class);
        $this->seed(\Database\Seeders\HcmPlatformSeeder::class);
        $this->seed(\Database\Seeders\HcmExtendedSeeder::class);

        $employee = Employee::first();
        $tenant = Tenant::first();
        $this->assertNotNull($employee);
        $this->assertNotNull($tenant);

        $cycle = PerformanceCycle::create([
            'tenant_id' => $tenant->id,
            'name' => 'Q1',
            'period' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
            'status' => 'active',
        ]);

        Goal::create([
            'performance_cycle_id' => $cycle->id,
            'employee_id' => $employee->id,
            'title' => 'Doanh số',
            'target_value' => 100,
            'actual_value' => 80,
            'weight' => 100,
            'status' => 'active',
        ]);

        $review = EmployeeReview::create([
            'performance_cycle_id' => $cycle->id,
            'employee_id' => $employee->id,
            'self_score' => 80,
            'manager_score' => 90,
            'status' => 'pending',
        ]);

        $res = $this->withHeaders($headers)
            ->postJson("/api/v1/employee-reviews/{$review->id}/finalize")
            ->assertOk();

        $final = $res->json('data.final_score');
        $this->assertNotNull($final);
        $this->assertSame('B', $res->json('data.rating'));
        $this->assertSame('completed', $res->json('data.status'));
    }
}
