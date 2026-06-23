<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceGeofenceZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $zoneId = $this->route('attendance_geofence_zone')?->id;

        return [
            'company_id' => ['required', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('attendance_geofence_zones', 'code')
                    ->where('company_id', $this->input('company_id'))
                    ->ignore($zoneId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'zone_type' => ['required', 'in:factory,office,warehouse,field_site'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:20', 'max:5000'],
            'allowed_sources' => ['nullable', 'array'],
            'allowed_sources.*' => ['in:mobile,device,kiosk,qr'],
            'is_active' => ['sometimes', 'boolean'],
            'address_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
