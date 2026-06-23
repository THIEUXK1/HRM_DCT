<?php

namespace App\Services\Company;

use App\Models\CompanyPolicyVersion;
use App\Models\CompanySetting;
use App\Models\EmployeePolicySetting;

/**
 * Đọc chính sách theo company_id — merge defaults + DB + (tuỳ chọn) version theo kỳ.
 */
class CompanyPolicyResolver
{
    /** @var array<string, self> */
    private static array $instances = [];

    private int $companyId;

    private ?string $period;

    private ?int $employeeId = null;

    /** @var array<string, string|null> */
    private array $cache = [];

    /** @var array<string, string>|null */
    private ?array $periodOverlay = null;

    /** @var array<string, string>|null */
    private ?array $employeeOverlay = null;

    private function __construct(int $companyId, ?string $period = null, ?int $employeeId = null)
    {
        $this->companyId = $companyId;
        $this->period = $period;
        $this->employeeId = $employeeId;
        if ($period) {
            $this->periodOverlay = $this->resolvePeriodOverlay($period);
        }
        if ($employeeId) {
            $this->employeeOverlay = $this->resolveEmployeeOverlay($period);
        }
    }

    public static function for(int $companyId, ?string $period = null, ?int $employeeId = null): self
    {
        $key = $companyId.':'.($period ?? '_').':'.($employeeId ?? '_');
        if (! isset(self::$instances[$key])) {
            self::$instances[$key] = new self($companyId, $period, $employeeId);
        }

        return self::$instances[$key];
    }

    public static function flushCache(): void
    {
        self::$instances = [];
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        if ($this->employeeOverlay !== null && array_key_exists($key, $this->employeeOverlay)) {
            return $this->cache[$key] = $this->employeeOverlay[$key];
        }

        if ($this->periodOverlay !== null && array_key_exists($key, $this->periodOverlay)) {
            return $this->cache[$key] = $this->periodOverlay[$key];
        }

        $db = CompanySetting::where('company_id', $this->companyId)
            ->where('key', $key)
            ->value('value');

        if ($db !== null) {
            return $this->cache[$key] = $db;
        }

        $fallback = config("company_policy_defaults.{$key}", $default);

        return $this->cache[$key] = $fallback;
    }

    public function getString(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        return (float) $this->get($key, $default);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $v = $this->get($key, $default ? '1' : '0');

        return in_array((string) $v, ['1', 'true', 'yes', 'on'], true);
    }

    /** @return array<string, string> */
    public function domain(string $domain): array
    {
        $keys = config("company_policy_domains.domains.{$domain}.keys", []);
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = (string) $this->get($key, '');
        }

        return $out;
    }

    /** @return array<string, string> */
    public function allManagedSettings(): array
    {
        $keys = [];
        foreach (config('company_policy_domains.domains', []) as $def) {
            foreach ($def['keys'] ?? [] as $key) {
                $keys[] = $key;
            }
        }

        $out = [];
        foreach (array_unique($keys) as $key) {
            $out[$key] = (string) $this->get($key, '');
        }

        return $out;
    }

    public function payrollLegal(string $path, mixed $default = null): mixed
    {
        return data_get(config('payroll_vn', []), $path, $default);
    }

    /** @return array<string, string> */
    private function resolvePeriodOverlay(string $period): array
    {
        $periodStart = $period.'-01';
        $overlay = [];

        $versions = CompanyPolicyVersion::where('company_id', $this->companyId)
            ->where('effective_from', '<=', $periodStart)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        $seenDomains = [];
        foreach ($versions as $version) {
            if (isset($seenDomains[$version->domain])) {
                continue;
            }
            $seenDomains[$version->domain] = true;
            foreach ($version->snapshot_json ?? [] as $k => $v) {
                $overlay[$k] = (string) $v;
            }
        }

        return $overlay;
    }

    /** @return array<string, string> */
    private function resolveEmployeeOverlay(?string $period): array
    {
        if ($this->employeeId === null) {
            return [];
        }

        $asOf = $period
            ? $period.'-01'
            : now()->format('Y-m-d');

        $rows = EmployeePolicySetting::withoutGlobalScope('company')
            ->where('company_id', $this->companyId)
            ->where('employee_id', $this->employeeId)
            ->whereDate('effective_from', '<=', $asOf)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        $overlay = [];
        foreach ($rows as $row) {
            if (! array_key_exists($row->key, $overlay)) {
                $overlay[$row->key] = (string) $row->value;
            }
        }

        return $overlay;
    }
}
