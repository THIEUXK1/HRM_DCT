<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_awards_disciplines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('type'); // 'award' or 'discipline'
            $table->string('decision_number');
            $table->date('decision_date');
            $table->text('reason');
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('signed_by')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('decision_number');
            $table->date('effective_date');
            $table->string('type'); // 'promotion', 'transfer', 'demotion'
            $table->text('reason')->nullable();
            $table->string('signed_by')->nullable();
            $table->string('status')->default('pending'); // 'pending', 'approved', 'rejected'
            $table->foreignId('from_branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->foreignId('to_branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->foreignId('from_position_id')->nullable()->constrained('positions')->onDelete('set null');
            $table->foreignId('to_position_id')->nullable()->constrained('positions')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('employee_terminations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('decision_number');
            $table->date('termination_date');
            $table->text('reason')->nullable();
            $table->string('type'); // 'resignation', 'dismissal', 'retirement', 'redundancy'
            $table->string('signed_by')->nullable();
            $table->string('status')->default('pending'); // 'pending', 'approved', 'rejected'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_terminations');
        Schema::dropIfExists('employee_transfers');
        Schema::dropIfExists('employee_awards_disciplines');
    }
};
