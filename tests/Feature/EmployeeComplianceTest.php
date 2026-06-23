<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeComplianceTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        Role::create(['name' => 'admin']);
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('Admin@123'),
        ]);
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'admin-token'])->save();

        return ['Authorization' => 'Bearer admin-token'];
    }

    public function test_duplicate_national_id_rejected_within_tenant(): void
    {
        $headers = $this->adminHeaders();
        $tenant = Tenant::create(['code' => 'T1', 'name' => 'Tập đoàn', 'is_active' => true]);

        $companyA = Company::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cty A',
            'code' => 'A',
            'is_active' => true,
        ]);
        $companyB = Company::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cty B',
            'code' => 'B',
            'is_active' => true,
        ]);

        $payload = [
            'company_id' => $companyA->id,
            'employee_code' => 'E1',
            'first_name' => 'A',
            'last_name' => 'One',
            'full_name' => 'A One',
            'email' => 'a@test.local',
            'national_id' => '001090015234',
        ];

        $this->withHeaders($headers)->postJson('/api/v1/employees', $payload)->assertCreated();

        $payload['company_id'] = $companyB->id;
        $payload['employee_code'] = 'E2';
        $payload['email'] = 'b@test.local';

        $this->withHeaders($headers)->postJson('/api/v1/employees', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['national_id']);
    }

    public function test_employee_document_upload_and_download(): void
    {
        Storage::fake('hr_private');
        $headers = $this->adminHeaders();

        $company = Company::create(['name' => 'Co', 'code' => 'C1', 'is_active' => true]);
        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'E1',
            'first_name' => 'Test',
            'last_name' => 'User',
            'full_name' => 'Test User',
            'email' => 't@test.local',
        ]);

        $file = UploadedFile::fake()->create('cccd.pdf', 100, 'application/pdf');

        $response = $this->withHeaders($headers)->post("/api/v1/employees/{$employee->id}/documents", [
            'type' => 'cccd_front',
            'document_number' => '001090015234',
            'file' => $file,
        ]);

        $response->assertCreated();
        $docId = $response->json('data.id');

        $this->withHeaders($headers)
            ->get("/api/v1/employees/{$employee->id}/documents/{$docId}/download")
            ->assertOk();
    }

    public function test_bhxh_d01_export_returns_csv(): void
    {
        $headers = $this->adminHeaders();
        $tenant = Tenant::create(['code' => 'T-BHXH', 'name' => 'T', 'is_active' => true]);
        $company = Company::create([
            'tenant_id' => $tenant->id,
            'name' => 'Co',
            'code' => 'C1',
            'tax_code' => '0123456789',
            'social_insurance_unit_code' => 'DV001',
            'is_active' => true,
        ]);

        $candidate = \App\Models\Candidate::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'full_name' => 'Nguyen A',
            'email' => 'a@test.local',
            'stage' => 'offer',
        ]);

        \App\Models\Offer::create([
            'candidate_id' => $candidate->id,
            'salary_base' => 10_000_000,
            'start_date' => '2026-05-15',
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $hireRes = $this->withHeaders($headers)
            ->postJson("/api/v1/candidates/{$candidate->id}/hire", [])
            ->assertCreated();

        Employee::find($hireRes->json('data.id'))->update([
            'national_id' => '001090015234',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'bhxh_start_date' => '2026-05-15',
            'insurance_salary' => 10_000_000,
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/bhxh/export', [
                'company_id' => $company->id,
                'declaration_type' => 'd01',
                'format' => 'csv',
                'from' => '2026-05-01',
                'to' => '2026-05-31',
            ])
            ->assertOk()
            ->assertJsonPath('data.success', true);
    }

    public function test_employee_excel_export_and_import(): void
    {
        $headers = $this->adminHeaders();
        $company = Company::create(['name' => 'Co', 'code' => 'C1', 'is_active' => true]);
        
        Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'E-TEST-EXPORT',
            'first_name' => 'Export',
            'last_name' => 'User',
            'full_name' => 'Export User',
            'email' => 'export@test.local',
            'phone' => '0987654321',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'hire_date' => '2026-05-15',
            'is_active' => true,
        ]);

        // 1. Test Export
        $exportRes = $this->withHeaders(array_merge($headers, ['X-Company-Id' => $company->id]))
            ->get('/api/v1/employees/actions/export')
            ->assertOk();
        
        $xlsxContent = $exportRes->streamedContent();
        $tempFile = tempnam(sys_get_temp_dir(), 'export_test');
        file_put_contents($tempFile, $xlsxContent);

        $zip = new \ZipArchive();
        $sharedStrings = '';
        if ($zip->open($tempFile) === true) {
            $sharedStrings = $zip->getFromName('xl/sharedStrings.xml') ?: '';
            $zip->close();
        }
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        $this->assertStringContainsString('E-TEST-EXPORT', $sharedStrings);
        $this->assertStringContainsString('export@test.local', $sharedStrings);

        // 2. Test Import
        $importData = "\xEF\xBB\xBF" . "Mã nhân viên;Họ;Tên;Email cá nhân;Số điện thoại;Giới tính;Ngày sinh;Ngày vào làm\r\n" .
                      "E-TEST-IMPORT;Import;User;import@test.local;0987654322;Nam;01/01/1990;15/05/2026\r\n";
        
        $file = UploadedFile::fake()->createWithContent('import.csv', $importData);

        $importRes = $this->withHeaders(array_merge($headers, ['X-Company-Id' => $company->id]))
            ->post('/api/v1/employees/actions/import', [
                'file' => $file,
            ])
            ->assertOk();


        $this->assertEquals(1, $importRes->json('data.imported'));
        $this->assertDatabaseHas('employees', [
            'employee_code' => 'E-TEST-IMPORT',
            'email' => 'import@test.local',
        ]);
    }
}

