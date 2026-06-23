<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_terminations', function (Blueprint $table) {
            $table->string('reason_type')->nullable()->after('type');
            $table->date('effective_date')->nullable()->after('reason_type');
            $table->text('notes')->nullable()->after('effective_date');
            $table->boolean('handover_tasks_done')->default(false);
            $table->boolean('assets_returned')->default(false);
            $table->boolean('exit_interview_done')->default(false);
            $table->boolean('accounts_disabled')->default(false);
            $table->boolean('final_settlement_done')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('employee_terminations', function (Blueprint $table) {
            $table->dropColumn([
                'reason_type', 'effective_date', 'notes',
                'handover_tasks_done', 'assets_returned',
                'exit_interview_done', 'accounts_disabled', 'final_settlement_done',
            ]);
        });
    }
};
