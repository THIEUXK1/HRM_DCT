<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_requests', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('requested_by');
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
        });

        Schema::table('job_posts', function (Blueprint $table) {
            $table->longText('job_description')->nullable()->after('title');
            $table->string('external_url')->nullable()->after('channel');
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->text('experience_summary')->nullable()->after('notes');
            $table->json('skills')->nullable()->after('experience_summary');
            $table->timestamp('rejected_at')->nullable()->after('employee_id');
        });

        Schema::create('candidate_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('cv');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_disk')->default('hr_private');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('interview_feedbacks', function (Blueprint $table) {
            $table->json('scorecard')->nullable()->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('interview_feedbacks', function (Blueprint $table) {
            $table->dropColumn('scorecard');
        });
        Schema::dropIfExists('candidate_documents');
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['experience_summary', 'skills', 'rejected_at']);
        });
        Schema::table('job_posts', function (Blueprint $table) {
            $table->dropColumn(['job_description', 'external_url']);
        });
        Schema::table('recruitment_requests', function (Blueprint $table) {
            $table->dropColumn(['submitted_at', 'approved_at']);
        });
    }
};
