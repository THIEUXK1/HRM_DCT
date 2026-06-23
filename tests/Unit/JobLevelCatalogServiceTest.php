<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Services\Hr\JobLevelCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobLevelCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_syncs_o1_to_o7_with_four_bands(): void
    {
        $company = Company::create(['name' => 'Test Co', 'code' => 'TCO']);

        $result = app(JobLevelCatalogService::class)->syncStandardGrades($company->id);

        $this->assertSame(28, $result['created']);
        $this->assertDatabaseHas('job_levels', [
            'company_id' => $company->id,
            'code' => 'O1-A',
            'grade' => 'O1',
            'band' => 'A',
            'category' => 'manager',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('job_levels', [
            'company_id' => $company->id,
            'code' => 'O7-D',
            'category' => 'worker',
        ]);
    }
}
