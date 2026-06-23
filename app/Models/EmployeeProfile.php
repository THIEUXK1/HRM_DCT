<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'marital_status',
        'father_name',
        'mother_name',
        'spouse_name',
        'spouse_id_number',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'education_level',
        'education_institution',
        'graduation_year',
        'major',
        'professional_certificate',
        'experience_years',
        'skills',
        'certificate_summary',
        'military_service_status',
        'disability_level',
        'passport_number',
        'passport_expiry',
        'work_permit_number',
        'work_permit_expiry',
        'hobby',
        'profile_picture_path',
        'biometric_id',
        'card_number',
    ];

    protected function casts(): array
    {
        return [
            'experience_years' => 'integer',
            'graduation_year' => 'integer',
            'passport_expiry' => 'date',
            'work_permit_expiry' => 'date',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
