<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benefit_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('code', 50)->unique();
            $table->string('name');

            // Category: health | accident | phone | transport | meal | housing |
            //           equipment | childcare | bonus | other
            $table->string('category', 50)->default('other');
            $table->text('description')->nullable();

            // Value
            $table->string('value_type', 20)->default('fixed'); // fixed | percentage | reimbursement
            $table->decimal('value', 15, 2)->default(0);         // amount or %
            $table->string('currency', 10)->default('VND');

            // Eligibility
            $table->unsignedSmallInteger('eligible_after_days')->default(0); // 0 = từ ngày vào
            $table->boolean('is_taxable')->default(false);

            // Validity
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('company_id');
            $table->index(['company_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benefit_plans');
    }
};
