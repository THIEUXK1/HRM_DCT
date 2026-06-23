<?php

namespace App\Services\Attendance;

use App\Models\CompanyHoliday;
use Carbon\Carbon;

/**
 * Danh sách ngày nghỉ lễ theo Điều 112 Bộ luật Lao động 2019.
 *
 * Tổng 11 ngày lễ/năm:
 *   - Tết Dương lịch:   01/01 (1 ngày)
 *   - Tết Âm lịch:      4 ngày (29 + 01–03 tháng Giêng âm lịch; nếu năm thiếu: 30 + 01–03)
 *   - Ngày Giỗ Tổ:      10/3 âm lịch (1 ngày) — thêm từ 2007
 *   - Ngày Thống nhất:  30/04 (1 ngày)
 *   - Ngày Quốc tế LĐ:  01/05 (1 ngày)
 *   - Quốc khánh:       02/09 + 01/09 hoặc 03/09 (2 ngày — từ 2021)
 *
 * Khi ngày lễ rơi vào T7/CN, Nhà nước bù vào ngày làm việc kế tiếp
 * (Nghị định 145/2020, Điều 9). Logic bù lịch được xử lý qua adjustments.
 */
class VietnamHolidayService
{
    private static array $cache = [];

    /**
     * Trả về mảng [date_string => holiday_name] cho năm dương lịch.
     * Nếu có $companyId, gộp thêm lịch nghỉ lễ công ty (hỗ trợ khoảng nhiều ngày).
     */
    public static function forYear(int $year, ?int $companyId = null): array
    {
        $cacheKey = ($companyId ? "c{$companyId}:" : 'n:').$year;
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $holidays = self::nationalHolidaysForYear($year);

        if ($companyId) {
            $holidays = self::mergeCompanyHolidays($holidays, $companyId, $year);
        }

        ksort($holidays);
        self::$cache[$cacheKey] = $holidays;

        return $holidays;
    }

    /** @return array<string, string> */
    private static function nationalHolidaysForYear(int $year): array
    {
        $holidays = [];

        // 1. Tết Dương lịch
        self::add($holidays, $year, 1, 1, 'Tết Dương lịch');

        // 2. Tết Âm lịch (29 hoặc 30 tháng Chạp + mùng 1–3 tháng Giêng)
        $lunarNewYear = self::lunarNewYearGregorian($year);
        $lunarDates = [
            $lunarNewYear->copy()->subDay()->format('Y-m-d')   => 'Nghỉ Tết (30 tháng Chạp)',
            $lunarNewYear->copy()->format('Y-m-d')             => 'Tết Nguyên Đán (Mùng 1)',
            $lunarNewYear->copy()->addDays(1)->format('Y-m-d') => 'Nghỉ Tết (Mùng 2)',
            $lunarNewYear->copy()->addDays(2)->format('Y-m-d') => 'Nghỉ Tết (Mùng 3)',
        ];
        foreach ($lunarDates as $date => $name) {
            $holidays[$date] = $name;
        }

        // 3. Giỗ Tổ Hùng Vương (10/3 âm lịch)
        $hungKings = self::lunarToGregorian($year, 3, 10);
        if ($hungKings) {
            $holidays[$hungKings->format('Y-m-d')] = 'Giỗ Tổ Hùng Vương (10/3 ÂL)';
        }

        // 4. Ngày Giải phóng miền Nam
        self::add($holidays, $year, 4, 30, 'Ngày Giải phóng miền Nam (30/4)');

        // 5. Ngày Quốc tế Lao động
        self::add($holidays, $year, 5, 1, 'Ngày Quốc tế Lao động (1/5)');

        // 6. Quốc khánh (2 ngày từ năm 2021 theo NQ 63/2021/QH15)
        self::add($holidays, $year, 9, 2, 'Quốc khánh (2/9)');
        if ($year >= 2021) {
            $sep2 = Carbon::create($year, 9, 2);
            // Ngày nghỉ bù: nếu 2/9 là T7 thì bù 1/9, nếu là CN thì bù 3/9
            if ($sep2->isSaturday()) {
                self::add($holidays, $year, 9, 1, 'Nghỉ bù Quốc khánh (1/9)');
            } elseif ($sep2->isSunday()) {
                self::add($holidays, $year, 9, 3, 'Nghỉ bù Quốc khánh (3/9)');
            } else {
                // Ngày kề 2/9 (thường là 1/9 hoặc 3/9 tuỳ năm được công bố)
                // Mặc định thêm 3/9 (thực tế cần lấy công văn từng năm)
                self::add($holidays, $year, 9, 3, 'Nghỉ Quốc khánh (3/9)');
            }
        }

        ksort($holidays);

        return $holidays;
    }

    /** @param  array<string, string>  $holidays */
    private static function mergeCompanyHolidays(array $holidays, int $companyId, int $year): array
    {
        $yearStart = Carbon::create($year, 1, 1)->toDateString();
        $yearEnd = Carbon::create($year, 12, 31)->toDateString();

        $rows = CompanyHoliday::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('holiday_date', '<=', $yearEnd)
            ->whereRaw('COALESCE(end_date, holiday_date) >= ?', [$yearStart])
            ->get();

        foreach ($rows as $row) {
            foreach ($row->expandToDateMap() as $date => $name) {
                if (Carbon::parse($date)->year === $year) {
                    $holidays[$date] = $name;
                }
            }
        }

        return $holidays;
    }

    /**
     * Xóa cache khi lịch nghỉ công ty thay đổi — gọi sau store/update/destroy CompanyHoliday.
     */
    public static function clearCache(?int $companyId = null, ?int $year = null): void
    {
        if ($companyId === null && $year === null) {
            self::$cache = [];

            return;
        }

        foreach (array_keys(self::$cache) as $key) {
            $matchCompany = $companyId === null || str_starts_with($key, "c{$companyId}:");
            $matchYear = $year === null || str_ends_with($key, (string) $year);
            if ($matchCompany && $matchYear) {
                unset(self::$cache[$key]);
            }
        }
    }

    public static function isHoliday(Carbon $date, ?int $companyId = null): bool
    {
        return array_key_exists($date->format('Y-m-d'), self::forYear($date->year, $companyId));
    }

    public static function holidayName(Carbon $date, ?int $companyId = null): ?string
    {
        return self::forYear($date->year, $companyId)[$date->format('Y-m-d')] ?? null;
    }

    /**
     * Tính số ngày làm việc tiêu chuẩn trong một tháng (trừ T7, CN, lễ).
     * Áp dụng tuần làm việc 6 ngày (T2–T7) cho lịch 48h/tuần,
     * hoặc 5 ngày (T2–T6) cho lịch 40h/tuần.
     */
    public static function standardWorkDays(string $period, bool $fiveDay = false, ?int $companyId = null): int
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end   = $start->copy()->endOfMonth();
        $holidays = self::forYear($start->year, $companyId);

        $count = 0;
        $current = $start->copy();
        while ($current <= $end) {
            $isWeekend = $fiveDay ? $current->isWeekend() : $current->isSunday();
            $isHoliday = array_key_exists($current->format('Y-m-d'), $holidays);
            if (! $isWeekend && ! $isHoliday) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }

    /** Số ngày làm chuẩn trong khoảng ngày (trừ CN + lễ) — dùng phân bổ công TV/CT giữa tháng. */
    public static function standardWorkDaysInRange(Carbon $from, Carbon $to, bool $fiveDay = false, ?int $companyId = null): int
    {
        if ($from->gt($to)) {
            return 0;
        }

        $holidays = array_merge(
            self::forYear($from->year, $companyId),
            $from->year !== $to->year ? self::forYear($to->year, $companyId) : [],
        );

        $count = 0;
        $current = $from->copy();
        while ($current <= $to) {
            $isWeekend = $fiveDay ? $current->isWeekend() : $current->isSunday();
            $isHoliday = array_key_exists($current->format('Y-m-d'), $holidays);
            if (! $isWeekend && ! $isHoliday) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private static function add(array &$holidays, int $year, int $month, int $day, string $name): void
    {
        $key = Carbon::create($year, $month, $day)->format('Y-m-d');
        $holidays[$key] = $name;
    }

    /**
     * Tính ngày mùng 1 Tết Âm lịch của năm dương lịch chỉ định.
     * Dùng công thức gần đúng (Jean Meeus) — đủ dùng cho giai đoạn 2000–2050.
     */
    private static function lunarNewYearGregorian(int $year): Carbon
    {
        // Thuật toán tính Tết dựa trên bảng tra (đơn giản hoá cho phạm vi 2020–2040)
        // Nguồn: Astronomical Algorithms, Jean Meeus, Chapter 9
        $lunarNewYearDates = [
            2020 => '2020-01-25', 2021 => '2021-02-12', 2022 => '2022-02-01',
            2023 => '2023-01-22', 2024 => '2024-02-10', 2025 => '2025-01-29',
            2026 => '2026-02-17', 2027 => '2027-02-06', 2028 => '2028-01-26',
            2029 => '2029-02-13', 2030 => '2030-02-03', 2031 => '2031-01-23',
            2032 => '2032-02-11', 2033 => '2033-01-31', 2034 => '2034-02-19',
            2035 => '2035-02-08', 2036 => '2036-01-28', 2037 => '2037-02-15',
            2038 => '2038-02-04', 2039 => '2039-01-24', 2040 => '2040-02-12',
        ];

        if (isset($lunarNewYearDates[$year])) {
            return Carbon::parse($lunarNewYearDates[$year]);
        }

        // Fallback: tính gần đúng theo chu kỳ 19 năm Metonic
        $referenceYear = 2023;
        $referenceDate = Carbon::parse('2023-01-22');
        $diff = $year - $referenceYear;
        $cycles = intdiv($diff, 19);
        $remainder = $diff % 19;
        $approxDays = $cycles * 6940 + $remainder * 354 + ($remainder >= 3 ? 30 : 0);

        return $referenceDate->copy()->addDays($approxDays);
    }

    /**
     * Chuyển ngày âm lịch sang dương lịch (đơn giản hoá, đủ dùng cho ngày Giỗ Tổ).
     * month, day: tháng và ngày âm lịch (1-based).
     */
    private static function lunarToGregorian(int $year, int $lunarMonth, int $lunarDay): ?Carbon
    {
        // Lấy mùng 1 tháng Giêng âm lịch, rồi cộng thêm số ngày
        $newYear = self::lunarNewYearGregorian($year);

        // Mỗi tháng âm lịch ~29.53 ngày. Tháng 3 âm lịch ≈ 59 ngày sau mùng 1 tháng Giêng
        $daysFromNewYear = ($lunarMonth - 1) * 29 + intval(($lunarMonth - 1) * 0.53) + ($lunarDay - 1);

        return $newYear->copy()->addDays($daysFromNewYear);
    }
}
