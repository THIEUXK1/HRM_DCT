<?php

namespace App\Services\Bhxh;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDependent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Xuất dữ liệu kê khai BHXH / phụ lục thuế (mẫu D01 báo tăng, TK1 người phụ thuộc).
 * XML/CSV chuẩn hóa nội bộ — có thể map sang file import cổng IVAN/VSS khi triển khai thật.
 */
class BhxhExportService
{
    public function __construct(
        protected ?BhxhContributionCalculator $contributions = null
    ) {
        $this->contributions ??= new BhxhContributionCalculator;
    }

    public function employeesForRoster(Company $company): Collection
    {
        return Employee::query()
            ->with(['position', 'department', 'contracts' => fn ($q) => $q->where('status', 'active')->latest('start_date')])
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNotNull('social_insurance_number')
            ->orderBy('employee_code')
            ->get();
    }

    /** NV đã tham gia BHXH, có thay đổi mức lương đóng hoặc hồ sơ trong kỳ. */
    public function employeesForAdjustment(Company $company, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from ??= now()->startOfMonth();
        $to ??= now()->endOfMonth();

        return Employee::query()
            ->with(['position', 'contracts' => fn ($q) => $q->where('status', 'active')->latest('start_date')])
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNotNull('social_insurance_number')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('updated_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                    ->whereColumn('updated_at', '>', 'created_at');
            })
            ->where(function ($q) use ($from) {
                $q->whereNull('bhxh_start_date')
                    ->orWhere('bhxh_start_date', '<', $from->toDateString());
            })
            ->orderBy('employee_code')
            ->get();
    }

    public function insuranceBase(Employee $emp): int
    {
        $contract = $emp->relationLoaded('contracts') ? $emp->contracts->first() : null;

        return (int) ($emp->insurance_salary ?? $contract?->insurance_salary ?? $contract?->salary_base ?? 0);
    }

    public function employeesForIncrease(Company $company, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from ??= now()->startOfMonth();
        $to ??= now()->endOfMonth();

        return Employee::query()
            ->with(['position', 'department', 'contracts' => fn ($q) => $q->where('status', 'active')->latest('start_date')])
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('bhxh_start_date', [$from->toDateString(), $to->toDateString()])
                    ->orWhereBetween('hire_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->orderBy('employee_code')
            ->get();
    }

    public function dependentsForTk1(Company $company): Collection
    {
        return EmployeeDependent::query()
            ->with(['employee:id,full_name,tax_code,employee_code,national_id'])
            ->whereHas('employee', fn ($q) => $q->where('company_id', $company->id)->where('is_active', true))
            ->where('is_active', true)
            ->orderBy('employee_id')
            ->get();
    }

    public function employeesForDecrease(Company $company, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from ??= now()->startOfMonth();
        $to ??= now()->endOfMonth();

        return Employee::query()
            ->with(['position'])
            ->where('company_id', $company->id)
            ->whereIn('employment_status', ['terminated', 'resigned'])
            ->whereBetween('termination_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('employee_code')
            ->get();
    }

    public function toD01Csv(Company $company, Collection $employees): string
    {
        $lines = [];
        $lines[] = implode(';', [
            'STT', 'MaNV', 'HoTen', 'MaSoBHXH', 'NgaySinh', 'GioiTinh', 'CCCD',
            'MucLuongDong', 'NgayBatDau', 'ChucVu', 'NoiLamViec', 'LoaiHD', 'MaDonVi',
        ]);

        $i = 1;
        foreach ($employees as $emp) {
            $contract = $emp->contracts->first();
            $lines[] = implode(';', [
                $i++,
                $emp->employee_code,
                $this->csvCell($emp->full_name),
                $emp->social_insurance_number ?? '',
                $emp->date_of_birth?->format('d/m/Y') ?? '',
                $emp->gender === 'female' ? '2' : '1',
                $emp->national_id ?? '',
                $this->insuranceBase($emp),
                ($emp->bhxh_start_date ?? $emp->hire_date)?->format('d/m/Y') ?? '',
                $this->csvCell($emp->position?->name ?? $contract?->job_title_on_contract ?? ''),
                $this->csvCell($emp->work_location ?? $contract?->work_location ?? ''),
                $contract?->contract_type ?? $emp->employment_type ?? '',
                $company->social_insurance_unit_code ?? $company->code,
            ]);
        }

        return "\xEF\xBB\xBF".implode("\r\n", $lines);
    }

    public function toD01Xml(Company $company, Collection $employees): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('HoSoKhaiBao');
        $root->setAttribute('xmlns', 'http://vss.gov.vn/schema/ivan/d01');
        $doc->appendChild($root);

        $this->appendVssHeader($doc, $root, $company);

        $list = $doc->createElement('DanhSachNhanSu');
        $root->appendChild($list);

        foreach ($employees as $emp) {
            $contract = $emp->contracts->first();
            $node = $doc->createElement('NhanSu');
            $this->appendText($doc, $node, 'MaNV', $emp->employee_code);
            $this->appendText($doc, $node, 'HoTen', $emp->full_name);
            $this->appendText($doc, $node, 'MaSoBHXH', $emp->social_insurance_number);
            $this->appendText($doc, $node, 'NgaySinh', $emp->date_of_birth?->format('Y-m-d'));
            $this->appendText($doc, $node, 'GioiTinh', $emp->gender === 'female' ? '2' : '1');
            $this->appendText($doc, $node, 'CCCD', $emp->national_id);
            $this->appendText($doc, $node, 'MucLuongDong', (string) $this->insuranceBase($emp));
            $this->appendText($doc, $node, 'NgayBatDau', ($emp->bhxh_start_date ?? $emp->hire_date)?->format('Y-m-d'));
            $this->appendText($doc, $node, 'ChucVu', $emp->position?->name ?? $contract?->job_title_on_contract);
            $this->appendText($doc, $node, 'LoaiHopDong', $contract?->contract_type);
            $list->appendChild($node);
        }

        return $doc->saveXML();
    }

    public function toTk1Csv(Company $company, Collection $dependents): string
    {
        $lines = [];
        $lines[] = implode(';', [
            'STT', 'MaNV', 'MST_NLD', 'HoTen_NLD', 'HoTen_NPT', 'QuanHe', 'NgaySinh_NPT',
            'CCCD_NPT', 'MaNPT', 'TuNgay', 'DenNgay', 'MaDonVi',
        ]);

        $i = 1;
        foreach ($dependents as $dep) {
            $emp = $dep->employee;
            $lines[] = implode(';', [
                $i++,
                $emp->employee_code ?? '',
                $emp->tax_code ?? '',
                $this->csvCell($emp->full_name ?? ''),
                $this->csvCell($dep->full_name),
                $dep->relationship,
                $dep->date_of_birth?->format('d/m/Y') ?? '',
                $dep->id_card_number ?? '',
                $dep->tax_dependent_code ?? '',
                $dep->effective_from?->format('d/m/Y') ?? '',
                $dep->effective_to?->format('d/m/Y') ?? '',
                $company->social_insurance_unit_code ?? $company->code,
            ]);
        }

        return "\xEF\xBB\xBF".implode("\r\n", $lines);
    }

    public function toTk1Xml(Company $company, Collection $dependents): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('HoSoKhaiBao');
        $root->setAttribute('xmlns', 'http://vss.gov.vn/schema/ivan/tk1');
        $doc->appendChild($root);

        $this->appendVssHeader($doc, $root, $company);

        $list = $doc->createElement('DanhSachNguoiPhuThuoc');
        $root->appendChild($list);

        foreach ($dependents as $dep) {
            $emp = $dep->employee;
            $node = $doc->createElement('NguoiPhuThuoc');
            $this->appendText($doc, $node, 'MaNV', $emp->employee_code ?? '');
            $this->appendText($doc, $node, 'MST_NguoiLaoDong', $emp->tax_code);
            $this->appendText($doc, $node, 'HoTen_NguoiLaoDong', $emp->full_name);
            $this->appendText($doc, $node, 'HoTen_NPT', $dep->full_name);
            $this->appendText($doc, $node, 'QuanHe', $dep->relationship);
            $this->appendText($doc, $node, 'NgaySinh', $dep->date_of_birth?->format('Y-m-d'));
            $this->appendText($doc, $node, 'CCCD', $dep->id_card_number);
            $this->appendText($doc, $node, 'MaNPT', $dep->tax_dependent_code);
            $this->appendText($doc, $node, 'TuNgay', $dep->effective_from?->format('Y-m-d'));
            $this->appendText($doc, $node, 'DenNgay', $dep->effective_to?->format('Y-m-d'));
            $list->appendChild($node);
        }

        return $doc->saveXML();
    }

    public function toD05Csv(Company $company, Collection $employees): string
    {
        $lines = [];
        $lines[] = implode(';', [
            'STT', 'MaNV', 'HoTen', 'MaSoBHXH', 'CCCD', 'NgayNghi', 'LyDo', 'MaDonVi',
        ]);

        $i = 1;
        foreach ($employees as $emp) {
            $lines[] = implode(';', [
                $i++,
                $emp->employee_code,
                $this->csvCell($emp->full_name),
                $emp->social_insurance_number ?? '',
                $emp->national_id ?? '',
                $emp->termination_date?->format('d/m/Y') ?? '',
                $this->csvCell($emp->termination_reason ?? ''),
                $company->social_insurance_unit_code ?? $company->code,
            ]);
        }

        return "\xEF\xBB\xBF".implode("\r\n", $lines);
    }

    public function toD05Xml(Company $company, Collection $employees): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('HoSoKhaiBao');
        $root->setAttribute('xmlns', 'http://vss.gov.vn/schema/ivan/d05');
        $doc->appendChild($root);

        $this->appendVssHeader($doc, $root, $company);

        $list = $doc->createElement('DanhSachNhanSu');
        $root->appendChild($list);

        foreach ($employees as $emp) {
            $node = $doc->createElement('NhanSu');
            $this->appendText($doc, $node, 'MaNV', $emp->employee_code);
            $this->appendText($doc, $node, 'HoTen', $emp->full_name);
            $this->appendText($doc, $node, 'MaSoBHXH', $emp->social_insurance_number);
            $this->appendText($doc, $node, 'CCCD', $emp->national_id);
            $this->appendText($doc, $node, 'NgayNghi', $emp->termination_date?->format('Y-m-d'));
            $this->appendText($doc, $node, 'LyDo', $emp->termination_reason);
            $list->appendChild($node);
        }

        return $doc->saveXML();
    }

    public function toD02Csv(Company $company, Collection $employees): string
    {
        $lines = [];
        $lines[] = implode(';', [
            'STT', 'MaNV', 'HoTen', 'MaSoBHXH', 'CCCD', 'MucLuongCu', 'MucLuongMoi',
            'BHXH_NLD_Cu', 'BHXH_NLD_Moi', 'BHXH_DN_Cu', 'BHXH_DN_Moi', 'MaDonVi',
        ]);

        $i = 1;
        foreach ($employees as $emp) {
            $base = $this->insuranceBase($emp);
            $contrib = $this->contributions->forSalary($base);
            $lines[] = implode(';', [
                $i++,
                $emp->employee_code,
                $this->csvCell($emp->full_name),
                $emp->social_insurance_number ?? '',
                $emp->national_id ?? '',
                $base,
                $base,
                $contrib['bhxh_employee'],
                $contrib['bhxh_employee'],
                $contrib['bhxh_employer'],
                $contrib['bhxh_employer'],
                $company->social_insurance_unit_code ?? $company->code,
            ]);
        }

        return "\xEF\xBB\xBF".implode("\r\n", $lines);
    }

    public function toD02Xml(Company $company, Collection $employees): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('HoSoKhaiBao');
        $root->setAttribute('xmlns', 'http://vss.gov.vn/schema/ivan/d02');
        $doc->appendChild($root);

        $this->appendVssHeader($doc, $root, $company);

        $list = $doc->createElement('DanhSachDieuChinh');
        $root->appendChild($list);

        foreach ($employees as $emp) {
            $base = $this->insuranceBase($emp);
            $contrib = $this->contributions->forSalary($base);
            $node = $doc->createElement('NhanSu');
            $this->appendText($doc, $node, 'MaNV', $emp->employee_code);
            $this->appendText($doc, $node, 'HoTen', $emp->full_name);
            $this->appendText($doc, $node, 'MaSoBHXH', $emp->social_insurance_number);
            $this->appendText($doc, $node, 'MucLuongDong', (string) $base);
            $this->appendText($doc, $node, 'BHXH_NLD', (string) $contrib['bhxh_employee']);
            $this->appendText($doc, $node, 'BHXH_DN', (string) $contrib['bhxh_employer']);
            $list->appendChild($node);
        }

        return $doc->saveXML();
    }

    public function toRosterCsv(Company $company, Collection $employees): string
    {
        $lines = [];
        $lines[] = implode(';', [
            'STT', 'MaNV', 'HoTen', 'MaSoBHXH', 'CCCD', 'NgaySinh', 'MucLuongDong',
            'BHXH_NLD', 'BHYT_NLD', 'BHTN_NLD', 'Tong_NLD', 'BHXH_DN', 'BHYT_DN', 'BHTN_DN', 'KPCD_DN', 'Tong_DN', 'MaDonVi',
        ]);

        $i = 1;
        foreach ($employees as $emp) {
            $contrib = $this->contributions->forSalary($this->insuranceBase($emp));
            $lines[] = implode(';', [
                $i++,
                $emp->employee_code,
                $this->csvCell($emp->full_name),
                $emp->social_insurance_number ?? '',
                $emp->national_id ?? '',
                $emp->date_of_birth?->format('d/m/Y') ?? '',
                $contrib['insurance_base'],
                $contrib['bhxh_employee'],
                $contrib['bhyt_employee'],
                $contrib['bhtn_employee'],
                $contrib['employee_total'],
                $contrib['bhxh_employer'],
                $contrib['bhyt_employer'],
                $contrib['bhtn_employer'],
                $contrib['kpcd_employer'],
                $contrib['employer_total'],
                $company->social_insurance_unit_code ?? $company->code,
            ]);
        }

        return "\xEF\xBB\xBF".implode("\r\n", $lines);
    }

    public function generateContent(string $type, Company $company, Collection $records, string $format = 'csv'): string
    {
        return match ($type) {
            'd01' => $format === 'xml'
                ? $this->toD01Xml($company, $records)
                : $this->toD01Csv($company, $records),
            'd02' => $format === 'xml'
                ? $this->toD02Xml($company, $records)
                : $this->toD02Csv($company, $records),
            'd05' => $format === 'xml'
                ? $this->toD05Xml($company, $records)
                : $this->toD05Csv($company, $records),
            'tk1' => $format === 'xml'
                ? $this->toTk1Xml($company, $records)
                : $this->toTk1Csv($company, $records),
            'roster' => $this->toRosterCsv($company, $records),
            default => throw new \InvalidArgumentException("Unknown declaration type: {$type}"),
        };
    }

    public function resolveRecords(string $type, Company $company, ?Carbon $from, ?Carbon $to): Collection
    {
        return match ($type) {
            'd01' => $this->employeesForIncrease($company, $from, $to),
            'd02' => $this->employeesForAdjustment($company, $from, $to),
            'd05' => $this->employeesForDecrease($company, $from, $to),
            'tk1' => $this->dependentsForTk1($company),
            'roster' => $this->employeesForRoster($company),
            default => collect(),
        };
    }

    public function defaultFilename(string $type, Company $company, string $format): string
    {
        $map = ['d01' => 'D01-bao-tang', 'd02' => 'D02-dieu-chinh', 'd05' => 'D05-bao-giam', 'tk1' => 'TK1-phu-thuoc', 'roster' => 'DS-tham-gia'];
        $ext = $type === 'roster' ? 'csv' : $format;

        return ($map[$type] ?? $type)."-{$company->code}-".now()->format('Ymd').".{$ext}";
    }

    protected function csvCell(?string $value): string
    {
        $value = str_replace([';', "\r", "\n"], [' ', ' ', ' '], (string) $value);

        return '"'.$value.'"';
    }

    protected function appendText(\DOMDocument $doc, \DOMElement $parent, string $name, ?string $value): void
    {
        $el = $doc->createElement($name);
        $el->appendChild($doc->createTextNode((string) ($value ?? '')));
        $parent->appendChild($el);
    }

    protected function appendVssHeader(\DOMDocument $doc, \DOMElement $parent, Company $company): void
    {
        $header = $doc->createElement('ThongTinChung');
        $this->appendText($doc, $header, 'MaDonVi', $company->social_insurance_unit_code ?? $company->code);
        $this->appendText($doc, $header, 'TenDonVi', $company->name);
        $this->appendText($doc, $header, 'MaSoThue', $company->tax_code ?? '');
        $this->appendText($doc, $header, 'CoQuanBHXH', $company->social_insurance_agency ?? '');
        $this->appendText($doc, $header, 'NguoiDaiDien', $company->legal_representative ?? '');
        $this->appendText($doc, $header, 'NgayLap', now()->format('Y-m-d'));
        $parent->appendChild($header);
    }
}
