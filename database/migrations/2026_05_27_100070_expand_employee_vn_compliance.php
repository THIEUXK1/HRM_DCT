<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('id_card_type')->default('cccd')->after('national_id');
            $table->date('id_card_issue_date')->nullable()->after('id_card_type');
            $table->string('id_card_issue_place')->nullable()->after('id_card_issue_date');
            $table->date('id_card_expiry_date')->nullable()->after('id_card_issue_place');

            $table->string('nationality', 10)->default('VN')->after('country');
            $table->string('ethnicity')->nullable()->after('nationality');
            $table->string('religion')->nullable()->after('ethnicity');
            $table->string('place_of_birth')->nullable()->after('religion');
            $table->string('origin_place')->nullable()->after('place_of_birth');

            $table->text('permanent_address')->nullable()->after('origin_place');
            $table->text('temporary_address')->nullable()->after('permanent_address');
            $table->string('ward')->nullable()->after('temporary_address');
            $table->string('district')->nullable()->after('ward');
            $table->string('province')->nullable()->after('district');

            $table->string('employment_type')->default('full_time')->after('employment_status');
            $table->string('work_location')->nullable()->after('employment_type');
            $table->date('official_start_date')->nullable()->after('probation_end_date');
            $table->date('termination_date')->nullable()->after('official_start_date');
            $table->string('termination_reason')->nullable()->after('termination_date');

            $table->string('social_insurance_number')->nullable()->after('tax_code');
            $table->string('health_insurance_card')->nullable()->after('social_insurance_number');
            $table->date('bhxh_start_date')->nullable()->after('health_insurance_card');
            $table->unsignedBigInteger('insurance_salary')->nullable()->after('bhxh_start_date');
            $table->unsignedTinyInteger('pit_dependents_count')->default(0)->after('insurance_salary');
            $table->boolean('union_member')->default(false)->after('pit_dependents_count');

            $table->string('bank_account_name')->nullable()->after('bank_name');
            $table->string('bank_branch')->nullable()->after('bank_account_name');
            $table->string('personal_email')->nullable()->after('work_phone');
        });

        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->string('father_name')->nullable()->after('marital_status');
            $table->string('mother_name')->nullable()->after('father_name');
            $table->string('spouse_name')->nullable()->after('mother_name');
            $table->string('spouse_id_number')->nullable()->after('spouse_name');
            $table->string('education_institution')->nullable()->after('education_level');
            $table->unsignedSmallInteger('graduation_year')->nullable()->after('education_institution');
            $table->string('professional_certificate')->nullable()->after('major');
            $table->string('military_service_status')->nullable()->after('certificate_summary');
            $table->string('disability_level')->nullable()->after('military_service_status');
            $table->string('passport_number')->nullable()->after('disability_level');
            $table->date('passport_expiry')->nullable()->after('passport_number');
            $table->string('work_permit_number')->nullable()->after('passport_expiry');
            $table->date('work_permit_expiry')->nullable()->after('work_permit_number');
        });

        Schema::table('employee_documents', function (Blueprint $table) {
            $table->string('document_number')->nullable()->after('type');
            $table->string('issuing_authority')->nullable()->after('document_number');
        });

        Schema::create('employee_dependents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('relationship');
            $table->date('date_of_birth')->nullable();
            $table->string('id_card_number')->nullable();
            $table->string('tax_dependent_code')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_dependents');

        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropColumn(['document_number', 'issuing_authority']);
        });

        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'father_name', 'mother_name', 'spouse_name', 'spouse_id_number',
                'education_institution', 'graduation_year', 'professional_certificate',
                'military_service_status', 'disability_level',
                'passport_number', 'passport_expiry', 'work_permit_number', 'work_permit_expiry',
            ]);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'id_card_type', 'id_card_issue_date', 'id_card_issue_place', 'id_card_expiry_date',
                'nationality', 'ethnicity', 'religion', 'place_of_birth', 'origin_place',
                'permanent_address', 'temporary_address', 'ward', 'district', 'province',
                'employment_type', 'work_location', 'official_start_date', 'termination_date', 'termination_reason',
                'social_insurance_number', 'health_insurance_card', 'bhxh_start_date',
                'insurance_salary', 'pit_dependents_count', 'union_member',
                'bank_account_name', 'bank_branch', 'personal_email',
            ]);
        });
    }
};
