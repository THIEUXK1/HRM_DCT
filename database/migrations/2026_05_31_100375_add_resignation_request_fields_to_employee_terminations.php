<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_terminations', function (Blueprint $table) {
            $table->foreignId('submitted_by_user_id')->nullable()->after('employee_id')->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable()->after('submitted_by_user_id');
            $table->unsignedSmallInteger('notice_period_days')->nullable()->after('termination_date');
            $table->text('handover_note')->nullable()->after('reason');
            $table->text('rejection_reason')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('employee_terminations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by_user_id');
            $table->dropColumn([
                'requested_at',
                'notice_period_days',
                'handover_note',
                'rejection_reason',
            ]);
        });
    }
};
