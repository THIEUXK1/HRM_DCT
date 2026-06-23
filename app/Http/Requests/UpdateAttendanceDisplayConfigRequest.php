<?php

namespace App\Http\Requests;

use App\Services\Attendance\AttendanceDisplayConfigService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAttendanceDisplayConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'config' => ['required', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $config = $this->input('config', []);
            if (! is_array($config)) {
                return;
            }

            /** @var AttendanceDisplayConfigService $service */
            $service = app(AttendanceDisplayConfigService::class);
            $schema = $service->schema();

            foreach ($schema as $section => $codes) {
                if (! isset($config[$section]) || ! is_array($config[$section])) {
                    continue;
                }

                foreach ($codes as $code => $fields) {
                    if (! isset($config[$section][$code]) || ! is_array($config[$section][$code])) {
                        continue;
                    }

                    foreach ($fields as $field) {
                        if (! array_key_exists($field, $config[$section][$code])) {
                            continue;
                        }

                        $value = $config[$section][$code][$field];
                        $path = "config.{$section}.{$code}.{$field}";

                        if ($field === 'bold') {
                            if (! is_bool($value) && ! in_array($value, [0, 1, '0', '1', true, false], true)) {
                                $validator->errors()->add($path, 'Giá trị bold không hợp lệ.');
                            }
                            continue;
                        }

                        if (! is_string($value)) {
                            $validator->errors()->add($path, 'Giá trị phải là chuỗi.');

                            continue;
                        }

                        if (str_ends_with($field, '_color') || $field === 'bg_color' || $field === 'text_color' || $field === 'late_border_color') {
                            if ($value !== 'transparent' && ! preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                                $validator->errors()->add($path, 'Màu phải dạng #RRGGBB hoặc transparent.');
                            }
                        }
                    }
                }
            }
        });
    }
}
