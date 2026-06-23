<?php

namespace Tests\Feature;

use App\Models\Competency;
use App\Models\Course;
use App\Models\CourseCompetency;
use App\Models\Employee;
use App\Models\EmployeeCompetencyAssessment;
use App\Models\Goal;
use App\Models\PerformanceCycle;
use App\Models\TrainingClass;
use App\Models\TrainingEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LmsCompetencyAndReportsTest extends TestCase
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

    public function test_complete_training_syncs_competency_from_lms(): void
    {
        $headers = $this->headers();
        $this->seed(\Database\Seeders\InitialHrDataSeeder::class);
        $this->seed(\Database\Seeders\HcmPlatformSeeder::class);
        $this->seed(\Database\Seeders\HcmExtendedSeeder::class);

        $employee = Employee::first();
        $course = Course::where('code', 'HR-ONB-01')->first();
        $competency = Competency::where('code', 'COMM')->first();
        $class = TrainingClass::where('course_id', $course->id)->first();

        CourseCompetency::updateOrCreate(
            ['course_id' => $course->id, 'competency_id' => $competency->id],
            ['granted_level' => 4, 'min_score' => 70]
        );

        $enrollment = TrainingEnrollment::create([
            'training_class_id' => $class->id,
            'employee_id' => $employee->id,
            'status' => 'enrolled',
        ]);

        $res = $this->withHeaders($headers)
            ->postJson("/api/v1/training-enrollments/{$enrollment->id}/complete", ['score' => 85])
            ->assertOk();

        $this->assertNotEmpty($res->json('data.competency_updates'));

        $this->assertDatabaseHas('employee_competency_assessments', [
            'employee_id' => $employee->id,
            'competency_id' => $competency->id,
            'current_level' => 4,
            'source' => 'lms',
        ]);
    }

    public function test_competency_gap_report_lists_employees(): void
    {
        $headers = $this->headers();
        $this->seed(\Database\Seeders\InitialHrDataSeeder::class);
        $this->seed(\Database\Seeders\HcmPlatformSeeder::class);
        $this->seed(\Database\Seeders\HcmExtendedSeeder::class);

        $employee = Employee::first();
        $competency = Competency::first();
        EmployeeCompetencyAssessment::create([
            'employee_id' => $employee->id,
            'competency_id' => $competency->id,
            'current_level' => 2,
            'assessed_at' => now()->toDateString(),
            'source' => 'manual',
        ]);

        $res = $this->withHeaders($headers)
            ->getJson('/api/v1/reports/competency-gaps')
            ->assertOk();

        $this->assertGreaterThan(0, count($res->json('data.employees')));
    }

    public function test_performance_kpi_report_returns_cycle_summary(): void
    {
        $headers = $this->headers();
        $this->seed(\Database\Seeders\InitialHrDataSeeder::class);
        $this->seed(\Database\Seeders\HcmPlatformSeeder::class);
        $this->seed(\Database\Seeders\HcmExtendedSeeder::class);

        $employee = Employee::first();
        $cycle = PerformanceCycle::first();

        Goal::create([
            'performance_cycle_id' => $cycle->id,
            'employee_id' => $employee->id,
            'title' => 'Doanh thu',
            'target_value' => 100,
            'actual_value' => 90,
            'weight' => 100,
        ]);

        $res = $this->withHeaders($headers)
            ->getJson('/api/v1/reports/performance-kpi')
            ->assertOk();

        $this->assertNotNull($res->json('data.cycle'));
        $this->assertSame(90.0, (float) $res->json('data.summary.avg_kpi_score'));
    }

    public function test_workforce_movement_report_returns_period_summary(): void
    {
        $headers = $this->headers();
        $this->seed(\Database\Seeders\InitialHrDataSeeder::class);
        $this->seed(\Database\Seeders\HcmPlatformSeeder::class);

        $period = now()->format('Y-m');

        $res = $this->withHeaders($headers)
            ->getJson("/api/v1/reports/workforce-movement?period={$period}")
            ->assertOk();

        $this->assertSame($period, $res->json('data.period'));
        $this->assertArrayHasKey('headcount_start', $res->json('data.summary'));
        $this->assertArrayHasKey('movement_rate', $res->json('data.summary'));
    }

    public function test_executive_summary_report_aggregates_sections(): void
    {
        $headers = $this->headers();
        $this->seed(\Database\Seeders\InitialHrDataSeeder::class);
        $this->seed(\Database\Seeders\HcmPlatformSeeder::class);

        $res = $this->withHeaders($headers)
            ->getJson('/api/v1/reports/executive-summary')
            ->assertOk();

        $this->assertArrayHasKey('headline', $res->json('data'));
        $this->assertArrayHasKey('sections', $res->json('data'));
        $this->assertArrayHasKey('payroll', $res->json('data.sections'));
        $this->assertNotEmpty($res->json('data.comments'));
    }
}
