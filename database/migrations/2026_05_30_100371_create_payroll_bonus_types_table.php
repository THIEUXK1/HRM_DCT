<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_bonus_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('category', 32)->default('adhoc');
            $table->string('breakdown_key', 64)->nullable();
            $table->boolean('taxable')->default(true);
            $table->boolean('counts_in_gross')->default(true);
            $table->string('calculation_mode', 32)->default('manual');
            $table->decimal('default_rate', 8, 4)->nullable();
            $table->unsignedBigInteger('default_amount')->nullable();
            $table->string('legal_reference')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_bonus_types');
    }
};
