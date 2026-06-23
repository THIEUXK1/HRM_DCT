<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ghi đè chính sách công ty theo từng nhân viên (áp dụng 1 hoặc nhiều NV).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_policy_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 32);
            $table->string('key', 64);
            $table->text('value');
            $table->date('effective_from');
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'key', 'effective_from'], 'employee_policy_key_effective_unique');
            $table->index(['company_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_policy_settings');
    }
};
