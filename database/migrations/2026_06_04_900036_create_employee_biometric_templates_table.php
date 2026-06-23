<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_biometric_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedTinyInteger('finger_index'); // 0–9 (ngón tay)
            $table->longText('template');               // base64 của binary template ZKTeco
            $table->unsignedBigInteger('source_device_id')->nullable(); // máy pull về
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'finger_index'], 'emp_biometric_finger_unique');
            $table->index('company_id', 'ebio_company_idx');

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('source_device_id')->references('id')->on('attendance_devices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_biometric_templates');
    }
};
