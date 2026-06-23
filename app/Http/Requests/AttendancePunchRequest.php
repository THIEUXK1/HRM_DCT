<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendancePunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->employee;
    }

    public function rules(): array
    {
        return [
            'punch_type' => ['required', 'in:in,out'],
            'latitude' => ['required_without_all:zone_code,gate_token', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_without_all:zone_code,gate_token', 'nullable', 'numeric', 'between:-180,180'],
            'zone_code' => ['required_with:gate_token', 'nullable', 'string', 'max:32'],
            'gate_token' => ['required_with:zone_code', 'nullable', 'string', 'max:64'],
            'qr_payload' => ['nullable', 'string', 'max:255'],
            'accuracy_meters' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'source' => ['sometimes', 'in:mobile,kiosk,qr'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('qr_payload') && ! $this->filled('zone_code')) {
            $parsed = \App\Models\AttendanceGeofenceZone::parseQrPayload($this->input('qr_payload'));
            if ($parsed) {
                $this->merge([
                    'zone_code' => $parsed['zone_code'],
                    'gate_token' => $parsed['gate_token'],
                    'source' => $this->input('source', 'qr'),
                ]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'latitude.required_without_all' => 'Bật GPS hoặc quét mã QR tại cổng để chấm công.',
            'longitude.required_without_all' => 'Bật GPS hoặc quét mã QR tại cổng để chấm công.',
            'punch_type.in' => 'Loại chấm công phải là vào (in) hoặc ra (out).',
        ];
    }
}
