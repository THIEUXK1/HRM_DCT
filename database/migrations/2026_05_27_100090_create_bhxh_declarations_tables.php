<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bhxh_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('declaration_type', 20);
            $table->string('period', 20)->nullable();
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->string('format', 10)->default('csv');
            $table->unsignedInteger('record_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->string('status', 30)->default('exported');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_disk')->default('hr_private');
            $table->json('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'declaration_type', 'created_at']);
        });

        Schema::create('bhxh_declaration_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bhxh_declaration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('line_no')->default(0);
            $table->json('payload')->nullable();
            $table->json('validation_errors')->nullable();
            $table->boolean('is_valid')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bhxh_declaration_lines');
        Schema::dropIfExists('bhxh_declarations');
    }
};
