<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biến số tùy chỉnh cho công thức lương (kế toán tự thêm, không cần sửa code).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_formula_custom_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('label');
            $table->decimal('value', 16, 4)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_formula_custom_variables');
    }
};
