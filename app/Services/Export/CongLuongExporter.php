<?php



namespace App\Services\Export;



use App\Models\AttendanceSummary;

use App\Models\Company;

use App\Models\EmployeePayrollAllowance;

use App\Models\PayrollCycle;

use App\Models\PayrollResult;

use App\Services\Payroll\CongLuongSheetService;

use Illuminate\Support\Collection;



/**

 * Xuất Excel « công và lương » — format BestPacific (mẫu cong-va-luong-mau.xlsx).

 */

class CongLuongExporter

{

    public function __construct(

        private readonly CongLuongSheetService $sheetService,

    ) {}



    public function download(int $companyId, string $period): \Symfony\Component\HttpFoundation\StreamedResponse

    {

        $company = Company::findOrFail($companyId);

        $xlsx = new SimpleXlsxWriter();

        $xlsx->addSheet('Công', $this->buildCongSheet($company, $companyId, $period), $this->congColWidths());

        $xlsx->addSheet('Lương', $this->buildLuongSheet($companyId, $period), $this->luongColWidths());



        $filename = sprintf(

            'cong-luong-%s-%s.xlsx',

            strtoupper($company->code ?? 'CTY'),

            $period,

        );



        return $xlsx->download($filename);

    }



    /** @return list<list<mixed>> */

    private function buildCongSheet(Company $company, int $companyId, string $period): array

    {

        $report = $this->sheetService->report($companyId, $period);

        $rows = [

            ["BẢNG CÔNG THÁNG {$period} — {$company->name}"],

            ['Xuất từ EHR · Mẫu BestPacific · storage/app/templates/cong-va-luong-mau.xlsx'],

            $this->headerRow('cong_columns'),

        ];



        foreach ($report['cong']['rows'] as $row) {

            $rows[] = $this->congRowToSparse($row);

        }



        return $rows;

    }



    /** @return list<list<mixed>> */

    private function buildLuongSheet(int $companyId, string $period): array

    {

        $report = $this->sheetService->report($companyId, $period);

        $rows = [

            ['BẢNG PHỤ CẤP / LƯƠNG THÁNG '.$period],

            ['Xuất từ EHR · Mẫu BestPacific'],

            $this->headerRow('luong_columns'),

        ];



        foreach ($report['luong']['rows'] as $row) {

            $rows[] = $this->luongRowToSparse($row);

        }



        return $rows;

    }



    /** @return list<mixed> */

    private function headerRow(string $configKey): array

    {

        $values = [];

        foreach (config("cong_luong_sheet.{$configKey}", []) as $col => $meta) {
            $values[$col] = (string) ($meta['label_vi'] ?? $meta['label'] ?? $col);
        }



        return $this->sparseRow($values);

    }



    /** @param  array<string, mixed>  $row */

    private function congRowToSparse(array $row): array

    {

        $otMap = [

            'ot_night_weekday' => 'night_weekday',

            'ot_night_weekend' => 'night_weekend',

            'ot_night_paid_holiday' => 'night_paid_holiday',

            'ot_day_weekday' => 'day_weekday',

            'ot_day_weekend' => 'day_weekend',

            'ot_day_annual_leave' => 'day_annual_leave',

            'ot_night_annual_leave' => 'night_annual_leave',

            'ot_day_holiday' => 'day_holiday',

            'ot_night_holiday' => 'night_holiday',

        ];



        $leaveMap = [

            'leave_annual' => 'annual',

            'leave_personal' => 'personal',

            'leave_wedding' => 'wedding',

            'leave_maternity' => 'maternity',

            'leave_funeral' => 'funeral',

            'leave_sick' => 'sick',

            'leave_unauthorized' => 'unauthorized',

            'leave_company' => 'company',

        ];



        $values = [

            'A' => $row['stt'] ?? '',

            'B' => $row['employee_code'] ?? '',

            'D' => $row['full_name'] ?? '',

            'F' => $row['department'] ?? '',

            'G' => $row['job_level'] ?? '',

            'H' => $row['payable_work_days'] ?? 0,

            'I' => $row['paid_holiday_leave_days'] ?? 0,

            'J' => $row['minimum_wage_days'] ?? 0,

            'K' => $row['base_salary_paid_leave_days'] ?? 0,

            'L' => $row['holiday_days'] ?? 0,

            'M' => $row['night_hours_summary'] ?? 0,

            'N' => $row['business_trip_days'] ?? 0,

            'O' => $row['menstrual_leave_hours'] ?? 0,

            'AG' => ($row['travel_support_flag'] ?? '-') === 'Có' ? 'Có' : '-',

            'AH' => $row['saturday_duty_hours'] ?? 0,

            'AI' => $row['resignation_note'] ?? '',

            'AJ' => $row['resignation_days'] ?? 0,

            'AK' => $row['days_not_joined'] ?? 0,

            'AL' => $row['join_date'] ?? '',

            'AM' => $row['standard_work_days'] ?? 0,

            'AN' => $row['employment_status'] ?? '',

            'AO' => $row['employment_active'] ?? 'Đang làm việc',

        ];



        foreach ($otMap as $rowKey => $colKey) {

            $col = array_search($colKey, config('cong_luong_import.cong_ot_columns', []), true);

            if ($col) {

                $values[$col] = $row[$rowKey] ?? 0;

            }

        }



        foreach ($leaveMap as $rowKey => $colKey) {

            $col = array_search($colKey, config('cong_luong_import.cong_leave_columns', []), true);

            if ($col) {

                $values[$col] = $row[$rowKey] ?? 0;

            }

        }



        return $this->sparseRow($values);

    }



    /** @param  array<string, mixed>  $row */

    private function luongRowToSparse(array $row): array

    {

        $values = [

            'A' => $row['stt'] ?? '',

            'B' => $row['employee_code'] ?? '',

            'D' => $row['full_name'] ?? '',

            'F' => $row['department'] ?? '',

            'G' => $row['job_level'] ?? '',

            'H' => $row['base_salary'] ?? 0,

            'R' => $row['travel_support_amount'] ?? 0,

            'S' => $row['travel_eligible'] ?? '-',

            'AB' => $row['notes'] ?? '',

        ];



        foreach (config('cong_luong_import.luong_allowance_columns', []) as $col => $code) {

            $values[$col] = $row[$code] ?? 0;

        }



        return $this->sparseRow($values);

    }



    /** @param  array<string, mixed>  $columnValues */

    private function sparseRow(array $columnValues): array

    {

        $maxIdx = 0;

        foreach (array_keys($columnValues) as $col) {

            $maxIdx = max($maxIdx, $this->colToIndex($col));

        }



        $row = array_fill(0, $maxIdx + 1, '');

        foreach ($columnValues as $col => $value) {

            $row[$this->colToIndex($col)] = $value;

        }



        return $row;

    }



    private function colToIndex(string $col): int

    {

        $col = strtoupper($col);

        $index = 0;

        $len = strlen($col);

        for ($i = 0; $i < $len; $i++) {

            $index = $index * 26 + (ord($col[$i]) - 64);

        }



        return $index - 1;

    }



    /** @return list<int> */

    private function congColWidths(): array

    {

        return array_fill(0, 42, 10);

    }



    /** @return list<int> */

    private function luongColWidths(): array

    {

        return array_fill(0, 32, 12);

    }

}


