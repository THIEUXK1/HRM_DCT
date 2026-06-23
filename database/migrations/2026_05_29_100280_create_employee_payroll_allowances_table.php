<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2b: trợ cấp tháng theo NV — mirror sheet lương BestPacific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_payroll_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->json('allowances')->nullable()
                ->comment('Mã trợ cấp → số tiền VND (config payroll_allowances.catalog)');
            $table->decimal('travel_support_amount', 15, 2)->default(0);
            $table->boolean('travel_eligible')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'period']);
            $table->index(['company_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payroll_allowances');
    }
};
