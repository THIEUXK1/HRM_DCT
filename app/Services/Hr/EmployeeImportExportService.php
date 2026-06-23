<?php

namespace App\Services\Hr;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Services\Export\SimpleXlsxReader;
use App\Services\Export\SimpleXlsxWriter;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use RuntimeException;

class EmployeeImportExportService
{
    /**
     * Xuất danh sách nhân viên hiện tại của công ty thành file CSV.
     */
    public function exportCsv(int $companyId): string
    {
        $employees = Employee::query()
            ->with(['branch', 'department', 'position'])
            ->where('company_id', $companyId)
            ->orderBy('employee_code')
            ->get();

        $handle = fopen('php://temp', 'r+');
        
        // Write UTF-8 BOM
        fwrite($handle, "\xEF\xBB\xBF");

        // Write header
        fputcsv($handle, [
            'STT',
            'Mã nhân viên',
            'Họ',
            'Tên',
            'Email cá nhân',
            'Số điện thoại',
            'Giới tính',
            'Ngày sinh',
            'Ngày vào làm',
            'Số CCCD',
            'Mã số thuế',
            'Mã số BHXH',
            'Mức lương đóng BHXH',
            'Số tài khoản',
            'Tên ngân hàng',
            'Chi nhánh',
            'Phòng ban',
            'Chức danh',
            'Trạng thái'
        ], ';');

        $i = 1;
        foreach ($employees as $emp) {
            fputcsv($handle, [
                $i++,
                $emp->employee_code,
                $emp->first_name,
                $emp->last_name,
                $emp->email,
                $emp->phone,
                $emp->gender === 'female' ? 'Nữ' : 'Nam',
                $emp->date_of_birth?->format('d/m/Y') ?? '',
                $emp->hire_date?->format('d/m/Y') ?? '',
                $emp->national_id,
                $emp->tax_code,
                $emp->social_insurance_number,
                $emp->insurance_salary ?? 0,
                $emp->bank_account,
                $emp->bank_name,
                $emp->branch?->name ?? '',
                $emp->department?->name ?? '',
                $emp->position?->name ?? '',
                $emp->employment_status === 'active' ? 'Đang làm việc' : 'Đã nghỉ việc'
            ], ';');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    /**
     * Nhập danh sách nhân viên từ file CSV.
     */
    public function importCsv(int $companyId, UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            throw new RuntimeException('Không thể mở file nhập dữ liệu.');
        }

        // Đọc BOM nếu có
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Tự động dò tìm delimiter (dấu phẩy hoặc dấu chấm phẩy)
        $firstLine = fgets($handle);
        $delimiter = ';';
        if (str_contains($firstLine, ',')) {
            $delimiter = ',';
        }
        
        // Quay lại đầu file sau khi đọc dòng đầu
        rewind($handle);
        if ($bom === "\xEF\xBB\xBF") {
            fread($handle, 3); // Bỏ qua BOM lần nữa
        }

        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            fclose($handle);
            throw new RuntimeException('File trống hoặc sai định dạng tiêu đề.');
        }

        // Làm sạch header
        $header = array_map(fn($item) => trim(str_replace('"', '', $item)), $header);

        $imported = 0;
        $skipped = 0;
        $errors = [];

        // Lấy chi nhánh mặc định đầu tiên hoặc tạo mới của công ty
        $defaultBranch = Branch::where('company_id', $companyId)->first();
        if (!$defaultBranch) {
            $defaultBranch = Branch::create([
                'company_id' => $companyId,
                'code' => 'BR-' . $companyId . '-DEF',
                'name' => 'Chi nhánh mặc định',
                'is_active' => true
            ]);
        }

        // Load cache để tránh truy vấn liên tục trong vòng lặp
        $departments = Department::where('branch_id', $defaultBranch->id)->get()->keyBy(fn($d) => strtolower($d->name));
        $positions = Position::get()->keyBy(fn($p) => strtolower($p->name));

        $rowNumber = 1;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;
            try {
                // Kết hợp header và row
                if (count($header) !== count($row)) {
                    // Nếu số lượng phần tử không khớp, pad hoặc cắt bớt
                    if (count($row) < count($header)) {
                        $row = array_pad($row, count($header), '');
                    } else {
                        $row = array_slice($row, 0, count($header));
                    }
                }
                $data = array_combine($header, $row);
                $data = array_map('trim', $data);

                $empCode = $data['Mã nhân viên'] ?? '';
                $firstName = $data['Họ'] ?? '';
                $lastName = $data['Tên'] ?? '';
                $email = $data['Email cá nhân'] ?? '';
                $phone = $data['Số điện thoại'] ?? '';
                $genderStr = $data['Giới tính'] ?? 'Nam';
                $dobStr = $data['Ngày sinh'] ?? '';
                $hireDateStr = $data['Ngày vào làm'] ?? '';
                $nationalId = $data['Số CCCD'] ?? '';
                $taxCode = $data['Mã số thuế'] ?? '';
                $insuranceNum = $data['Mã số BHXH'] ?? '';
                $insuranceSalary = (int)($data['Mức lương đóng BHXH'] ?? 0);
                $bankAccount = $data['Số tài khoản'] ?? '';
                $bankName = $data['Tên ngân hàng'] ?? '';
                $deptName = $data['Phòng ban'] ?? '';
                $posName = $data['Chức danh'] ?? '';

                if (empty($empCode) || empty($firstName) || empty($lastName) || empty($email)) {
                    $skipped++;
                    $errors[] = "Dòng $rowNumber: Thiếu thông tin bắt buộc (Mã NV, Họ, Tên, hoặc Email).";
                    continue;
                }

                // Kiểm tra trùng mã nhân viên hoặc email cá nhân trong công ty
                $exists = Employee::where('company_id', $companyId)
                    ->where(function ($q) use ($empCode, $email) {
                        $q->where('employee_code', $empCode)
                          ->orWhere('email', $email);
                    })->first();

                if ($exists) {
                    $skipped++;
                    $errors[] = "Dòng $rowNumber: Trùng mã nhân viên hoặc email ($empCode / $email).";
                    continue;
                }

                // Xử lý Phòng ban (Department)
                $deptNameLower = strtolower($deptName);
                if (empty($deptName)) {
                    $deptId = null;
                } else {
                    if (!$departments->has($deptNameLower)) {
                        $newDept = Department::create([
                            'branch_id' => $defaultBranch->id,
                            'name' => $deptName,
                            'code' => 'DEP-' . strtoupper(str_replace(' ', '', $deptName)) . '-' . rand(100, 999),
                            'is_active' => true
                        ]);
                        $departments->put($deptNameLower, $newDept);
                    }
                    $deptId = $departments->get($deptNameLower)->id;
                }

                // Xử lý Chức danh (Position)
                $posNameLower = strtolower($posName);
                if (empty($posName)) {
                    $posId = null;
                } else {
                    if (!$positions->has($posNameLower)) {
                        $newPos = Position::create([
                            'department_id' => $deptId ?? $departments->first()?->id,
                            'name' => $posName,
                            'code' => 'POS-' . strtoupper(str_replace(' ', '', $posName)) . '-' . rand(100, 999),
                            'is_active' => true
                        ]);
                        $positions->put($posNameLower, $newPos);
                    }
                    $posId = $positions->get($posNameLower)->id;
                }

                // Định dạng Ngày
                $dateOfBirth = null;
                if (!empty($dobStr)) {
                    try {
                        $dateOfBirth = Carbon::createFromFormat('d/m/Y', $dobStr)->toDateString();
                    } catch (\Throwable $t) {
                        try {
                            $dateOfBirth = Carbon::parse($dobStr)->toDateString();
                        } catch (\Throwable $t2) {
                            $dateOfBirth = null;
                        }
                    }
                }

                $hireDate = null;
                if (!empty($hireDateStr)) {
                    try {
                        $hireDate = Carbon::createFromFormat('d/m/Y', $hireDateStr)->toDateString();
                    } catch (\Throwable $t) {
                        try {
                            $hireDate = Carbon::parse($hireDateStr)->toDateString();
                        } catch (\Throwable $t2) {
                            $hireDate = now()->toDateString();
                        }
                    }
                } else {
                    $hireDate = now()->toDateString();
                }

                // Insert Employee
                Employee::create([
                    'company_id' => $companyId,
                    'branch_id' => $defaultBranch->id,
                    'department_id' => $deptId,
                    'position_id' => $posId,
                    'employee_code' => $empCode,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'full_name' => trim("$firstName $lastName"),
                    'email' => $email,
                    'phone' => $phone,
                    'gender' => strtolower($genderStr) === 'nữ' ? 'female' : 'male',
                    'date_of_birth' => $dateOfBirth,
                    'hire_date' => $hireDate,
                    'national_id' => $nationalId,
                    'tax_code' => $taxCode,
                    'social_insurance_number' => $insuranceNum,
                    'insurance_salary' => $insuranceSalary,
                    'bank_account' => $bankAccount,
                    'bank_name' => $bankName,
                    'employment_status' => 'active',
                    'is_active' => true
                ]);

                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "Dòng $rowNumber: Lỗi hệ thống khi lưu - " . $e->getMessage();
            }
        }

        fclose($handle);

        return compact('imported', 'skipped', 'errors');
    }

    /**
     * Tạo file XLSX mẫu để người dùng tải về và điền dữ liệu nhân viên.
     */
    public function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = [
            'Mã nhân viên *', 'Họ *', 'Tên *', 'Email *',
            'Số điện thoại', 'Giới tính (Nam/Nữ)', 'Ngày sinh (dd/mm/yyyy)', 'Ngày vào làm (dd/mm/yyyy)',
            'Số CCCD/CMND', 'Ngày cấp CCCD (dd/mm/yyyy)', 'Nơi cấp CCCD',
            'Mã số thuế cá nhân', 'Số sổ BHXH', 'Lương đóng BHXH (VND)',
            'Số tài khoản ngân hàng', 'Tên ngân hàng', 'Chi nhánh ngân hàng',
            'Phòng ban', 'Chức danh/Vị trí',
            'Địa chỉ thường trú', 'Tỉnh/Thành phố',
            'Số người phụ thuộc (PIT)', 'Ghi chú',
        ];

        $example = [
            'EMP-001', 'Nguyễn Văn', 'An', 'an.nguyen@company.com',
            '0901234567', 'Nam', '15/03/1990', '01/01/2024',
            '001090123456', '20/05/2021', 'Cục Cảnh sát QLHC về TTXH',
            '8012345678', '0100123456', '10000000',
            '1234567890', 'Vietcombank', 'CN Hà Nội',
            'Kỹ thuật - Công nghệ', 'Lập trình viên',
            'Số 1 Đường ABC, Phường XYZ', 'Hà Nội',
            '1', '',
        ];

        $guide = [
            ['HƯỚNG DẪN NHẬP LIỆU HỒ SƠ NHÂN VIÊN'],
            [''],
            ['Quy tắc chung:'],
            ['  - Các cột có dấu (*) là bắt buộc. Để trống sẽ bị bỏ qua.'],
            ['  - Không xóa hoặc thay đổi tên cột trên dòng tiêu đề (dòng 1).'],
            ['  - Mã nhân viên và Email phải là duy nhất trong hệ thống.'],
            ['  - Nếu Phòng ban / Chức danh chưa tồn tại, hệ thống sẽ tự tạo mới.'],
            [''],
            ['Cột', 'Quy tắc / Giá trị hợp lệ'],
            ['Giới tính', '"Nam" hoặc "Nữ"'],
            ['Ngày sinh / Ngày vào làm / Ngày cấp CCCD', 'Định dạng dd/mm/yyyy  (ví dụ: 15/03/1990)'],
            ['Lương đóng BHXH', 'Số tiền VND, chỉ nhập số nguyên (ví dụ: 10000000)'],
            ['Số người phụ thuộc (PIT)', 'Số nguyên >= 0'],
            ['Phòng ban', 'Đúng tên phòng ban trong hệ thống, hoặc tên mới (tự động tạo)'],
            ['Chức danh/Vị trí', 'Đúng tên chức danh trong hệ thống, hoặc tên mới (tự động tạo)'],
        ];

        $colWidths = array_combine(range(0, 22), [
            15, 15, 12, 28, 14, 18, 22, 22,
            18, 22, 28, 18, 16, 22, 22, 18, 22,
            24, 24, 32, 18, 20, 20,
        ]);

        return (new SimpleXlsxWriter())
            ->addSheet('Hồ sơ nhân viên', [$headers, $example], $colWidths)
            ->addSheet('Hướng dẫn', $guide, [0 => 35, 1 => 55])
            ->download('mau-ho-so-nhan-vien.xlsx');
    }

    /**
     * Import nhân viên từ file XLSX theo template chuẩn (23 cột).
     * Tương thích ngược với file 10 cột cũ (cột thiếu mặc định rỗng).
     */
    public function importXlsx(int $companyId, UploadedFile $file): array
    {
        $reader = new SimpleXlsxReader();
        $rows   = $reader->readSheet($file->getRealPath(), skipRows: 1);

        $defaultBranch = Branch::where('company_id', $companyId)->first()
            ?? Branch::create([
                'company_id' => $companyId, 'code' => "BR-{$companyId}-DEF",
                'name' => 'Chi nhánh mặc định', 'is_active' => true,
            ]);

        $departments = Department::where('branch_id', $defaultBranch->id)->get()->keyBy(fn ($d) => strtolower($d->name));
        $positions   = Position::get()->keyBy(fn ($p) => strtolower($p->name));

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($rows as $rowIdx => $row) {
            $rowNum = $rowIdx + 2;

            try {
                $col = fn (int $i) => trim((string) ($row[$i] ?? ''));

                $empCode         = $col(0);
                $firstName       = $col(1);
                $lastName        = $col(2);
                $email           = $col(3);
                $phone           = $col(4);
                $genderStr       = $col(5) ?: 'Nam';
                $dobStr          = $col(6);
                $hireDateStr     = $col(7);
                $nationalId      = $col(8);
                $idIssueDateStr  = $col(9);
                $idIssuePlace    = $col(10);
                $taxCode         = $col(11);
                $insuranceNum    = $col(12);
                $insuranceSalary = (int) ($row[13] ?? 0);
                $bankAccount     = $col(14);
                $bankName        = $col(15);
                $bankBranch      = $col(16);
                $deptName        = $col(17);
                $posName         = $col(18);
                $address         = $col(19);
                $city            = $col(20);
                $pitDependents   = max(0, (int) ($row[21] ?? 0));
                $note            = $col(22);

                if ($empCode === '' || $firstName === '' || $lastName === '' || $email === '') {
                    $skipped++;
                    $errors[] = "Dòng {$rowNum}: Thiếu thông tin bắt buộc (Mã NV, Họ, Tên, Email).";
                    continue;
                }

                $exists = Employee::where('company_id', $companyId)
                    ->where(fn ($q) => $q->where('employee_code', $empCode)->orWhere('email', $email))
                    ->exists();

                if ($exists) {
                    $skipped++;
                    $errors[] = "Dòng {$rowNum}: Trùng mã NV hoặc email ({$empCode} / {$email}).";
                    continue;
                }

                // Phòng ban
                $deptId = null;
                if ($deptName) {
                    $key = strtolower($deptName);
                    if (! $departments->has($key)) {
                        $d = Department::create([
                            'branch_id' => $defaultBranch->id,
                            'name'      => $deptName,
                            'code'      => 'DEP-' . strtoupper(str_replace(' ', '', $deptName)) . '-' . rand(100, 999),
                            'is_active' => true,
                        ]);
                        $departments->put($key, $d);
                    }
                    $deptId = $departments->get($key)->id;
                }

                // Chức danh
                $posId = null;
                if ($posName) {
                    $key = strtolower($posName);
                    if (! $positions->has($key)) {
                        $p = Position::create([
                            'department_id' => $deptId ?? $departments->first()?->id,
                            'name'          => $posName,
                            'code'          => 'POS-' . strtoupper(str_replace(' ', '', $posName)) . '-' . rand(100, 999),
                            'is_active'     => true,
                        ]);
                        $positions->put($key, $p);
                    }
                    $posId = $positions->get($key)->id;
                }

                Employee::create([
                    'company_id'          => $companyId,
                    'branch_id'           => $defaultBranch->id,
                    'department_id'       => $deptId,
                    'position_id'         => $posId,
                    'employee_code'       => $empCode,
                    'first_name'          => $firstName,
                    'last_name'           => $lastName,
                    'full_name'           => trim("{$firstName} {$lastName}"),
                    'email'               => $email,
                    'phone'               => $phone ?: null,
                    'gender'              => in_array(strtolower($genderStr), ['nữ', 'female', 'f']) ? 'female' : 'male',
                    'date_of_birth'       => $this->parseDate($dobStr),
                    'hire_date'           => $this->parseDate($hireDateStr) ?? now()->toDateString(),
                    'national_id'         => $nationalId ?: null,
                    'id_card_issue_date'  => $this->parseDate($idIssueDateStr),
                    'id_card_issue_place' => $idIssuePlace ?: null,
                    'tax_code'            => $taxCode ?: null,
                    'social_insurance_number' => $insuranceNum ?: null,
                    'insurance_salary'    => $insuranceSalary ?: null,
                    'bank_account'        => $bankAccount ?: null,
                    'bank_name'           => $bankName ?: null,
                    'bank_branch'         => $bankBranch ?: null,
                    'address'             => $address ?: null,
                    'city'                => $city ?: null,
                    'pit_dependents_count' => $pitDependents,
                    'note'                => $note ?: null,
                    'employment_status'   => 'active',
                    'is_active'           => true,
                ]);

                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "Dòng {$rowNum}: " . $e->getMessage();
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    private function parseDate(string $raw): ?string
    {
        if (empty($raw)) return null;
        // Already yyyy-mm-dd (from XLSX reader)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw;
        foreach (['d/m/Y', 'd-m-Y', 'Y/m/d'] as $fmt) {
            try { return Carbon::createFromFormat($fmt, $raw)->toDateString(); } catch (\Throwable) {}
        }
        try { return Carbon::parse($raw)->toDateString(); } catch (\Throwable) {}
        return null;
    }
}
