<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_journal_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('mapping_type'); // salary, employee_insurance, employer_insurance, kpcd, union_fee
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('debit_account');
            $table->string('credit_account');
            $table->timestamps();
        });

        Schema::create('payroll_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number')->unique();
            $table->string('description');
            $table->date('entry_date');
            $table->string('accounting_regime'); // TT99_2025, TT200_2014
            $table->string('status')->default('draft'); // draft, posted
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_journal_entry_id')->constrained('payroll_journal_entries')->cascadeOnDelete();
            $table->string('debit_account');
            $table->string('credit_account');
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->string('description');
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_journal_lines');
        Schema::dropIfExists('payroll_journal_entries');
        Schema::dropIfExists('payroll_journal_mappings');
    }
};
