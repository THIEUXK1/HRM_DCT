<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benefit_enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('benefit_plan_id');
            $table->string('status', 20)->default('active'); // active | suspended | expired | cancelled

            $table->date('enrolled_at');
            $table->date('expires_at')->nullable();

            // Override value (nếu NV này có mức khác gói chuẩn)
            $table->decimal('override_value', 15, 2)->nullable();
            $table->string('notes')->nullable();

            // Who enrolled
            $table->unsignedBigInteger('enrolled_by')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'benefit_plan_id']);
            $table->index('benefit_plan_id');
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benefit_enrollments');
    }
};
