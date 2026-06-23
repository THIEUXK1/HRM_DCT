<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('social_insurance_unit_code')->nullable()->after('tax_code');
            $table->string('social_insurance_agency')->nullable()->after('social_insurance_unit_code');
            $table->string('legal_representative')->nullable()->after('social_insurance_agency');
        });

        Schema::table('employment_contracts', function (Blueprint $table) {
            $table->date('signed_date')->nullable()->after('end_date');
            $table->string('job_title_on_contract')->nullable()->after('contract_type');
            $table->string('work_location')->nullable()->after('job_title_on_contract');
            $table->unsignedBigInteger('probation_salary')->nullable()->after('salary_base');
            $table->unsignedBigInteger('insurance_salary')->nullable()->after('probation_salary');
            $table->text('allowance_note')->nullable()->after('insurance_salary');
            $table->unsignedSmallInteger('contract_duration_months')->nullable()->after('probation_months');
            $table->unsignedTinyInteger('revision_number')->default(1)->after('contract_duration_months');
            $table->string('signed_by_employer')->nullable()->after('work_schedule');
            $table->string('signed_by_employee')->nullable()->after('signed_by_employer');
            $table->string('file_name')->nullable()->after('file_path');
            $table->string('file_disk')->default('hr_private')->after('file_name');
            $table->string('mime_type')->nullable()->after('file_disk');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
            $table->text('notes')->nullable()->after('file_size');
        });

        Schema::table('employee_documents', function (Blueprint $table) {
            $table->string('file_disk')->default('hr_private')->after('file_path');
            $table->string('mime_type')->nullable()->after('file_disk');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
            $table->foreignId('uploaded_by')->nullable()->after('file_size')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by');
            $table->dropColumn(['file_disk', 'mime_type', 'file_size']);
        });

        Schema::table('employment_contracts', function (Blueprint $table) {
            $table->dropColumn([
                'signed_date', 'job_title_on_contract', 'work_location',
                'probation_salary', 'insurance_salary', 'allowance_note',
                'contract_duration_months', 'revision_number',
                'signed_by_employer', 'signed_by_employee',
                'file_name', 'file_disk', 'mime_type', 'file_size', 'notes',
            ]);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['social_insurance_unit_code', 'social_insurance_agency', 'legal_representative']);
        });
    }
};
