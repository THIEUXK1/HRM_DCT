<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import 2442 nhân viên từ employee.xlsb → employees_import.json.
 * Chạy: php artisan db:seed --class=EmployeeImportSeeder
 *
 * Quy trình:
 * 1. Xóa dữ liệu NV cũ của công ty (company_id=1)
 * 2. Tạo departments từ danh sách xlsb (chuẩn hóa hoa/thường)
 * 3. Tạo positions theo (department + chức vụ)
 * 4. Tạo employees + employee_profiles + employment_contracts + allowances
 */
class EmployeeImportSeeder extends Seeder
{
    private const COMPANY_ID = 1;
    private const BRANCH_ID  = 1;

    // Chuẩn hóa tên phòng ban (key = lowercase trim, value = tên chuẩn)
    private const DEPT_NORMALIZE = [
        'dệt đai'                          => 'Dệt Đai',
        'dệt ngang'                        => 'Dệt Ngang',
        'dệt ngang - s'                    => 'Dệt Ngang - S',
        'dệt dọc'                          => 'Dệt Dọc',
        'nhuộm vải'                        => 'Nhuộm Vải',
        'in hoa'                           => 'In Hoa',
        'qc vải'                           => 'QC Vải',
        'qc đai'                           => 'QC Đai',
        'phòng kiểm nghiệm'                => 'Phòng Kiểm Nghiệm',
        'công trình'                       => 'Công Trình',
        'định hình'                        => 'Định Hình',
        'chi nhánh hưng yên - dệt ngang'  => 'Chi Nhánh Hưng Yên - Dệt Ngang',
        'chi nhánh hưng yên - dệt đai'    => 'Chi Nhánh Hưng Yên - Dệt Đai',
    ];

    // Chuẩn hóa tên chức vụ
    private const POS_NORMALIZE = [
        'giám đốc' => 'Giám Đốc',
    ];

    public function run(): void
    {
        $jsonPath = base_path('../employees_import.json');
        if (! file_exists($jsonPath)) {
            $this->command->error("File not found: {$jsonPath}");
            return;
        }

        $employees = json_decode(file_get_contents($jsonPath), true);
        $this->command->info('Loaded ' . count($employees) . ' employees from JSON.');

        DB::statement('PRAGMA foreign_keys = OFF');
        try {
            $this->clearExistingData();
            $deptMap = $this->createDepartments($employees);
            $posMap  = $this->createPositions($employees, $deptMap);
            $this->importEmployees($employees, $deptMap, $posMap);
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }

        $count = DB::table('employees')->where('company_id', self::COMPANY_ID)->count();
        $this->command->info("Import xong. Tổng NV: {$count}");
    }

    private function clearExistingData(): void
    {
        $this->command->info('Xóa dữ liệu cũ...');

        $empIds = DB::table('employees')
            ->where('company_id', self::COMPANY_ID)
            ->pluck('id');

        if ($empIds->isNotEmpty()) {
            $relatedTables = [
                'employee_payroll_allowances',
                'employment_contracts',
                'employee_profiles',
                'employee_dependents',
                'leave_requests',
                'overtime_requests',
                'attendance_logs',
                'attendance_summaries',
                'leave_entitlements',
                'employee_onboarding_tasks',
                'employee_award_disciplines',
                'employee_transfers',
                'employee_terminations',
                'employee_decisions',
            ];
            foreach ($relatedTables as $table) {
                try {
                    DB::table($table)->whereIn('employee_id', $empIds)->delete();
                } catch (\Throwable) {
                    // table không tồn tại hoặc không có employee_id — bỏ qua
                }
            }
            DB::table('employees')->whereIn('id', $empIds)->delete();
        }

        DB::table('positions')
            ->whereIn('department_id', function ($q) {
                $q->select('id')->from('departments')->where('branch_id', self::BRANCH_ID);
            })->delete();

        DB::table('departments')->where('branch_id', self::BRANCH_ID)->delete();

        $this->command->info('Đã xóa xong.');
    }

    /** @return array<string, int> deptNormalizedName → dept_id */
    private function createDepartments(array $employees): array
    {
        $this->command->info('Tạo departments...');

        $names = collect($employees)
            ->pluck('department')
            ->filter()
            ->unique()
            ->values();

        $deptMap = [];
        $seq = 1;

        foreach ($names as $rawName) {
            $canonical = $this->normalizeDept($rawName);
            if (isset($deptMap[$canonical])) {
                continue; // đã tạo, alias mapping phía dưới
            }

            $code = 'D-' . str_pad($seq++, 3, '0', STR_PAD_LEFT);
            $id = DB::table('departments')->insertGetId([
                'branch_id'  => self::BRANCH_ID,
                'name'       => $canonical,
                'code'       => $code,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $deptMap[$canonical] = $id;
        }

        // Thêm alias mapping cho các tên biến thể (rawName → dept_id)
        $aliasMap = [];
        foreach ($names as $rawName) {
            $canonical = $this->normalizeDept($rawName);
            $aliasMap[mb_strtolower(trim($rawName))] = $deptMap[$canonical];
        }

        $this->command->info('  → ' . count($deptMap) . ' departments đã tạo.');
        return $aliasMap;
    }

    /** @return array<string, int> "deptId:posName" → position_id */
    private function createPositions(array $employees, array $deptMap): array
    {
        $this->command->info('Tạo positions...');

        $pairs = collect($employees)
            ->map(fn ($e) => [
                'dept_key' => mb_strtolower(trim($e['department'] ?? '')),
                'pos_name' => $this->normalizePos($e['position_vn'] ?? ''),
            ])
            ->filter(fn ($p) => $p['dept_key'] && $p['pos_name'])
            ->unique(fn ($p) => $p['dept_key'] . '||' . $p['pos_name']);

        $posMap = [];
        $seq = 1;

        foreach ($pairs as $pair) {
            $deptId = $deptMap[$pair['dept_key']] ?? null;
            if (! $deptId) continue;

            $key  = $deptId . ':' . $pair['pos_name'];
            $code = 'P-' . str_pad($seq++, 4, '0', STR_PAD_LEFT);

            $id = DB::table('positions')->insertGetId([
                'department_id' => $deptId,
                'name'          => $pair['pos_name'],
                'code'          => $code,
                'is_active'     => 1,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            $posMap[$key] = $id;
        }

        $this->command->info('  → ' . count($posMap) . ' positions đã tạo.');
        return $posMap;
    }

    private function importEmployees(array $employees, array $deptMap, array $posMap): void
    {
        $this->command->info('Import nhân viên...');

        $bar = $this->command->getOutput()->createProgressBar(count($employees));
        $bar->start();

        $now = now()->toDateTimeString();
        $errors = 0;
        $usedEmails = [];

        foreach ($employees as $row) {
            try {
                $this->importOne($row, $deptMap, $posMap, $now, $usedEmails);
            } catch (\Throwable $e) {
                $errors++;
                $this->command->newLine();
                $this->command->warn("  Lỗi [{$row['employee_code']}]: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        if ($errors > 0) {
            $this->command->warn("  Có {$errors} lỗi trong quá trình import.");
        }
    }

    private function importOne(array $row, array $deptMap, array $posMap, string $now, array &$usedEmails): void
    {
        $code    = trim($row['employee_code'] ?? '');
        $name    = trim($row['full_name'] ?? '');
        if (! $code || ! $name) return;

        $deptKey = mb_strtolower(trim($row['department'] ?? ''));
        $deptId  = $deptMap[$deptKey] ?? null;
        $posName = $this->normalizePos($row['position_vn'] ?? '');
        $posKey  = $deptId ? "{$deptId}:{$posName}" : null;
        $posId   = $posKey ? ($posMap[$posKey] ?? null) : null;

        $rawEmail = $this->cleanEmail($row['email'] ?? '');
        // Nếu email đã dùng bởi NV khác → dùng placeholder code-based
        if ($rawEmail && isset($usedEmails[$rawEmail])) {
            $rawEmail = null;
        }
        $email = $rawEmail ?: "{$code}@bestpacific.local";
        $usedEmails[$email] = true;

        $gender = match (mb_strtolower(trim($row['gender'] ?? ''))) {
            'nữ', 'nu', 'female', 'f' => 'female',
            'nam', 'male', 'm'        => 'male',
            default                   => null,
        };

        $status = match (trim($row['status'] ?? '')) {
            'Chính thức' => 'active',
            'Thử việc'   => 'probation',
            'Đào tạo'    => 'training',
            default      => 'active',
        };

        $nameParts = $this->splitName($name);

        $empId = DB::table('employees')->insertGetId([
            'company_id'          => self::COMPANY_ID,
            'branch_id'           => self::BRANCH_ID,
            'department_id'       => $deptId,
            'position_id'         => $posId,
            'employee_code'       => $code,
            'first_name'          => $nameParts['first'],
            'last_name'           => $nameParts['last'],
            'full_name'           => $name,
            'email'               => $email,
            'phone'               => $this->clean($row['phone']),
            'personal_email'      => $this->cleanEmail($row['email'] ?? ''),
            'gender'              => $gender,
            'date_of_birth'       => $this->parseDate($row['date_of_birth']),
            'hire_date'           => $this->parseDate($row['hire_date']),
            'probation_end_date'  => $this->parseDate($row['probation_contract_end']),
            'employment_status'   => $status,
            'is_active'           => $status !== 'terminated' ? 1 : 0,
            'national_id'         => $this->clean($row['cccd']),
            'old_national_id'     => $this->clean($row['cmnd']),
            'id_card_type'        => 'cccd',
            'id_card_issue_date'  => $this->parseDate($row['id_issue_date']),
            'id_card_issue_place' => $this->clean($row['id_issue_place']),
            'ethnicity'           => $this->clean($row['ethnicity']),
            'nationality'         => $this->mapNationality($row['nationality'] ?? ''),
            'address'             => $this->clean($row['current_address']),
            'ward'                => $this->clean($row['addr_ward']),
            'district'            => $this->clean($row['addr_district']),
            'province'            => $this->clean($row['addr_province']),
            'permanent_address'   => $this->buildPermanentAddress($row),
            'social_insurance_number' => $this->clean($row['bhxh_number']),
            'bhxh_start_date'     => $this->parseMonthYear($row['bhxh_start_month']),
            'bhxh_stop_date'      => $this->parseMonthYear($row['bhxh_stop_month']),
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        $this->insertProfile($empId, $row, $now);
        $this->insertContracts($empId, $code, $row, $now);
        $this->insertHousingAllowance($empId, $row, $now);
    }

    private function insertProfile(int $empId, array $row, string $now): void
    {
        DB::table('employee_profiles')->insert([
            'employee_id'              => $empId,
            'education_level'          => $this->clean($row['education_level']),
            'education_institution'    => $this->clean($row['school']),
            'major'                    => $this->clean($row['major']),
            'graduation_year'          => $this->cleanInt($row['graduation_year']),
            'emergency_contact_name'   => $this->clean($row['emergency_contact_name']),
            'emergency_contact_phone'  => $this->clean($row['emergency_contact_phone']),
            'created_at'               => $now,
            'updated_at'               => $now,
        ]);
    }

    private function insertContracts(int $empId, string $code, array $row, string $now): void
    {
        $contractTypes = DB::table('contract_types')->pluck('id', 'code');

        $contracts = [
            [
                'start' => $row['probation_contract_date'],
                'end'   => $row['probation_contract_end'],
                'type'  => 'HD-TV',
                'seq'   => 0,
            ],
            [
                'start' => $row['contract1_start'],
                'end'   => $row['contract1_end'],
                'type'  => null,
                'seq'   => 1,
            ],
            [
                'start' => $row['contract2_start'],
                'end'   => $row['contract2_end'],
                'type'  => null,
                'seq'   => 2,
            ],
            [
                'start' => $row['contract3_start'],
                'end'   => null,
                'type'  => 'HD-VTH',
                'seq'   => 3,
            ],
        ];

        foreach ($contracts as $c) {
            $start = $this->parseDate($c['start']);
            if (! $start) continue;

            $end = $this->parseDate($c['end']);

            if ($c['type'] === null) {
                // Tự suy loại HĐ theo thời hạn
                if ($end) {
                    $months = Carbon::parse($start)->diffInMonths(Carbon::parse($end));
                    $c['type'] = $months <= 12 ? 'HD-1Y' : 'HD-2Y';
                } else {
                    $c['type'] = 'HD-VTH';
                }
            }

            $typeId = $contractTypes[$c['type']] ?? $contractTypes['definite'] ?? null;
            $isLast = $c['seq'] === 3 || (! $end);
            $status = $isLast ? 'active' : 'expired';

            DB::table('employment_contracts')->insert([
                'employee_id'     => $empId,
                'contract_number' => "{$code}-C{$c['seq']}",
                'contract_type'   => $c['type'],
                'start_date'      => $start,
                'end_date'        => $end,
                'status'          => $status,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }

    private function insertHousingAllowance(int $empId, array $row, string $now): void
    {
        $amount = (float) ($row['housing_allowance'] ?? 0);
        if ($amount <= 0) return;

        DB::table('employee_payroll_allowances')->insert([
            'company_id'  => self::COMPANY_ID,
            'employee_id' => $empId,
            'period'       => now()->format('Y-m'),
            'allowances'   => json_encode(['housing' => $amount]),
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function normalizeDept(string $raw): string
    {
        $key = mb_strtolower(trim($raw));
        return self::DEPT_NORMALIZE[$key] ?? $this->titleCaseVn($raw);
    }

    private function normalizePos(string $raw): string
    {
        if (! $raw) return '';
        $key = mb_strtolower(trim($raw));
        return self::POS_NORMALIZE[$key] ?? $this->titleCaseVn($raw);
    }

    private function titleCaseVn(string $str): string
    {
        return mb_convert_case(trim($str), MB_CASE_TITLE, 'UTF-8');
    }

    private function splitName(string $full): array
    {
        $parts = explode(' ', trim($full));
        return [
            'last'  => array_shift($parts),
            'first' => implode(' ', $parts) ?: $full,
        ];
    }

    private function parseDate(?string $val): ?string
    {
        if (! $val || trim($val) === '') return null;
        $val = trim(explode(',', $val)[0]); // lấy ngày đầu nếu có nhiều

        // Format YYYY/MM/DD or YYYY-MM-DD
        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $val, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        // Excel serial date float
        if (is_numeric($val)) {
            try {
                $base = Carbon::create(1899, 12, 30);
                return $base->addDays((int) $val)->format('Y-m-d');
            } catch (\Throwable) {}
        }

        return null;
    }

    /** Chuyển "MM/YYYY" → "YYYY-MM-01" */
    private function parseMonthYear(?string $val): ?string
    {
        if (! $val || trim($val) === '') return null;
        $val = trim(explode(',', $val)[0]);

        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $val, $m)) {
            return sprintf('%04d-%02d-01', $m[2], $m[1]);
        }

        return null;
    }

    private function clean(?string $val): ?string
    {
        $v = trim($val ?? '');
        return $v !== '' ? $v : null;
    }

    private function cleanEmail(?string $val): ?string
    {
        $v = trim($val ?? '');
        return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : null;
    }

    private function cleanInt(?string $val): ?int
    {
        $v = trim($val ?? '');
        return is_numeric($v) ? (int) $v : null;
    }

    private function mapNationality(?string $val): string
    {
        $v = mb_strtolower(trim($val ?? ''));
        return match (true) {
            str_contains($v, 'việt')   => 'VN',
            str_contains($v, 'trung')  => 'CN',
            $v === ''                  => 'VN',
            default                    => 'VN',
        };
    }

    private function buildPermanentAddress(array $row): ?string
    {
        $parts = array_filter([
            $this->clean($row['perm_street']),
            $this->clean($row['perm_ward']),
            $this->clean($row['perm_district']),
            $this->clean($row['perm_province2']),
        ]);
        return $parts ? implode(', ', $parts) : $this->clean($row['perm_province']);
    }
}
