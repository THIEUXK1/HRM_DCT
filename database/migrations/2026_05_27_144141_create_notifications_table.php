<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();

            // Type: contract_expiring | bhxh_due | birthday | leave_approved |
            //        leave_rejected | approval_pending | payroll_finalized |
            //        onboarding_due | transfer_approved | probation_ending | custom
            $table->string('type', 60);
            $table->string('title');
            $table->text('body')->nullable();

            // Polymorphic link to source entity
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();

            // Action URL in the SPA (e.g. /contracts/15)
            $table->string('action_url')->nullable();

            $table->string('priority')->default('normal'); // low | normal | high | urgent
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();       // when email/push was sent
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'company_id']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_notifications');
    }
};
