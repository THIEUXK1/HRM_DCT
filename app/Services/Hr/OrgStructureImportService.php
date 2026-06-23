<?php

namespace App\Services\Hr;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Position;
use App\Services\Export\SimpleXlsxReader;
use App\Support\CompanyContext;
use Illuminate\Http\UploadedFile;

class OrgStructureImportService
{
    /**
     * Import org structure entities from an uploaded file (XLSX or CSV).
     *
     * @return array{imported: int, skipped: int, errors: string[]}
     */
    public function import(string $type, UploadedFile $file): array
    {
        $rows = $this->parseFile($file);

        return match ($type) {
            'branches'    => $this->importBranches($rows),
            'departments' => $this->importDepartments($rows),
            'positions'   => $this->importPositions($rows),
            default       => ['imported' => 0, 'skipped' => 0, 'errors' => ["Loại import không hợp lệ: {$type}"]],
        };
    }

    // ── Branches ───────────────────────────────────────────────────────────

    /**
     * Col A: code, B: name, C: company_code_or_id, D: address
     */
    private function importBranches(array $rows): array
    {
        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        // Pre-load companies
        $companies = \App\Models\Company::all()->keyBy('code');

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $code = trim($row[0] ?? '');
            $name = trim($row[1] ?? '');

            if ($code === '' || $name === '') {
                $skipped++;
                continue;
            }

            $companyCode = trim($row[2] ?? '');
            $company = $companyCode !== ''
                ? $companies->get($companyCode)
                : null;

            if (! $company && CompanyContext::id()) {
                $company = $companies->firstWhere('id', CompanyContext::id());
            }

            if (! $company) {
                $errors[] = $companyCode !== ''
                    ? "Dòng {$line}: Không tìm thấy công ty mã «{$companyCode}»"
                    : "Dòng {$line}: Thiếu mã công ty (cột C) hoặc chưa chọn công ty trên header X-Company-Id";
                $skipped++;

                continue;
            }

            Branch::updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'name'      => $name,
                    'address'   => trim($row[3] ?? ''),
                    'is_active' => true,
                ]
            );

            $imported++;
        }

        return compact('imported', 'skipped', 'errors');
    }

    // ── Departments ────────────────────────────────────────────────────────

    /**
     * Col A: code, B: name, C: branch_code, D: parent_dept_code
     */
    private function importDepartments(array $rows): array
    {
        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        $companyId = CompanyContext::id();
        $branchQuery = Branch::query();
        if ($companyId) {
            $branchQuery->where('company_id', $companyId);
        }
        $branches = $branchQuery->get()->keyBy('code');

        $deptQuery = Department::query()->with('branch');
        if ($companyId) {
            $deptQuery->whereHas('branch', fn ($q) => $q->where('company_id', $companyId));
        }
        $departments = $deptQuery->get()->keyBy('code');

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $code = trim($row[0] ?? '');
            $name = trim($row[1] ?? '');

            if ($code === '' || $name === '') {
                $skipped++;
                continue;
            }

            $branchCode = trim($row[2] ?? '');
            $branch = $branches->get($branchCode);

            if (! $branch) {
                $errors[] = "Dòng {$line}: Không tìm thấy chi nhánh mã '{$branchCode}'";
                $skipped++;
                continue;
            }

            $parentCode = trim($row[3] ?? '');
            $parentId   = null;
            if ($parentCode !== '') {
                $parent = $departments->get($parentCode);
                if ($parent) {
                    $parentId = $parent->id;
                }
            }

            if ($parentId && Department::query()->find($parentId)?->branch_id !== $branch->id) {
                $errors[] = "Dòng {$line}: Phòng ban cha «{$parentCode}» không cùng chi nhánh «{$branchCode}»";
                $skipped++;

                continue;
            }

            $dept = Department::updateOrCreate(
                ['branch_id' => $branch->id, 'code' => $code],
                [
                    'name'                 => $name,
                    'parent_department_id' => $parentId,
                    'is_active'            => true,
                ]
            );

            // Refresh cache for parent lookups
            $departments->put($code, $dept);
            $imported++;
        }

        return compact('imported', 'skipped', 'errors');
    }

    // ── Positions ──────────────────────────────────────────────────────────

    /**
     * Col A: code, B: name, C: department_code, D: level
     */
    private function importPositions(array $rows): array
    {
        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        $companyId = CompanyContext::id();
        $deptQuery = Department::query();
        if ($companyId) {
            $deptQuery->whereHas('branch', fn ($q) => $q->where('company_id', $companyId));
        }
        $departments = $deptQuery->get()->keyBy('code');

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $code = trim($row[0] ?? '');
            $name = trim($row[1] ?? '');

            if ($code === '' || $name === '') {
                $skipped++;
                continue;
            }

            $deptCode = trim($row[2] ?? '');
            $dept = $departments->get($deptCode);

            if (! $dept) {
                $errors[] = "Dòng {$line}: Không tìm thấy phòng ban mã '{$deptCode}'";
                $skipped++;
                continue;
            }

            Position::updateOrCreate(
                ['department_id' => $dept->id, 'code' => $code],
                [
                    'name'      => $name,
                    'level'     => trim($row[3] ?? ''),
                    'is_active' => true,
                ]
            );

            $imported++;
        }

        return compact('imported', 'skipped', 'errors');
    }

    // ── File parsing ───────────────────────────────────────────────────────

    /**
     * Parse XLSX or CSV, skipping the header row.
     *
     * @return array<int, array<int, string>>
     */
    private function parseFile(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if ($ext === 'xlsx' || $ext === 'xls') {
            $reader = new SimpleXlsxReader();
            return $reader->readSheet($file->getRealPath(), skipRows: 1);
        }

        // CSV / TXT fallback
        $lines = [];
        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            return [];
        }

        $firstRow = true;
        while (($cols = fgetcsv($handle, 2048)) !== false) {
            if ($firstRow) { $firstRow = false; continue; } // skip header
            $lines[] = array_map('trim', $cols);
        }
        fclose($handle);

        return $lines;
    }
}
