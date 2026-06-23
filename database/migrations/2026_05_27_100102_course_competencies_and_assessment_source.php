<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('granted_level')->default(3);
            $table->decimal('min_score', 5, 2)->default(0);
            $table->timestamps();
            $table->unique(['course_id', 'competency_id']);
        });

        Schema::table('employee_competency_assessments', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('assessed_by');
            $table->foreignId('course_id')->nullable()->after('source')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_competency_assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_id');
            $table->dropColumn('source');
        });
        Schema::dropIfExists('course_competencies');
    }
};
