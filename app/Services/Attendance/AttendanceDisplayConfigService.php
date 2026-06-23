<?php

namespace App\Services\Attendance;

use App\Models\CompanySetting;
use App\Support\CompanyContext;

/**
 * Cấu hình màu sắc / nhãn bảng công — admin chỉnh qua Settings, không cần sửa code.
 */
class AttendanceDisplayConfigService
{
    public const SETTING_KEY = 'attendance_display_config';

    public function defaults(): array
    {
        return config('attendance_display', []);
    }

    public function forCompany(?int $companyId = null): array
    {
        $companyId ??= CompanyContext::id();
        $defaults = $this->defaults();

        if (! $companyId) {
            return $defaults;
        }

        $raw = CompanySetting::where('company_id', $companyId)
            ->where('key', self::SETTING_KEY)
            ->value('value');

        if (! $raw) {
            return $defaults;
        }

        $overrides = json_decode($raw, true);
        if (! is_array($overrides)) {
            return $defaults;
        }

        return $this->mergeConfig($defaults, $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function save(int $companyId, array $overrides): array
    {
        $defaults = $this->defaults();
        $sanitized = $this->sanitizeOverrides($defaults, $overrides);
        $merged = $this->mergeConfig($defaults, $sanitized);

        CompanySetting::updateOrCreate(
            ['company_id' => $companyId, 'key' => self::SETTING_KEY],
            ['value' => json_encode($sanitized, JSON_UNESCAPED_UNICODE)]
        );

        return $merged;
    }

    /**
     * @return array<string, array<string>>
     */
    public function schema(): array
    {
        $defaults = $this->defaults();
        $schema = [];

        foreach ($defaults as $section => $items) {
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $code => $fields) {
                if (! is_array($fields)) {
                    continue;
                }
                $schema[$section][$code] = array_keys($fields);
            }
        }

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $overrides
     */
    private function mergeConfig(array $defaults, array $overrides): array
    {
        foreach ($defaults as $section => $items) {
            if (! is_array($items) || ! isset($overrides[$section]) || ! is_array($overrides[$section])) {
                continue;
            }
            $defaults[$section] = $this->mergeSection($items, $overrides[$section]);
        }

        return $defaults;
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $overrides
     */
    private function mergeSection(array $defaults, array $overrides): array
    {
        foreach ($defaults as $code => $fields) {
            if (! is_array($fields) || ! isset($overrides[$code]) || ! is_array($overrides[$code])) {
                continue;
            }
            $defaults[$code] = array_merge($fields, array_intersect_key($overrides[$code], $fields));
        }

        return $defaults;
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $overrides
     */
    private function sanitizeOverrides(array $defaults, array $overrides): array
    {
        $sanitized = [];

        foreach ($defaults as $section => $items) {
            if (! is_array($items) || ! isset($overrides[$section]) || ! is_array($overrides[$section])) {
                continue;
            }

            foreach ($items as $code => $fields) {
                if (! is_array($fields) || ! isset($overrides[$section][$code]) || ! is_array($overrides[$section][$code])) {
                    continue;
                }

                $entry = [];
                foreach ($fields as $field => $defaultValue) {
                    if (! array_key_exists($field, $overrides[$section][$code])) {
                        continue;
                    }
                    $value = $overrides[$section][$code][$field];
                    if ($field === 'bold') {
                        $entry[$field] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        continue;
                    }
                    if (is_string($value)) {
                        $entry[$field] = trim($value);
                    }
                }

                if ($entry !== []) {
                    $sanitized[$section][$code] = $entry;
                }
            }
        }

        return $sanitized;
    }
}
