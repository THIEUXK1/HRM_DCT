<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('actor_name');
            $table->unsignedBigInteger('tenant_id')->nullable()->after('company_id');
            // created|updated|deleted|approved|rejected|exported|finalized|assigned|revoked|login
            $table->string('action_category')->nullable()->after('action');
            $table->string('description')->nullable()->after('action_category');

            $table->index(['company_id', 'entity_type']);
            $table->index(['actor_id', 'created_at']);
            $table->index('action_category');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['company_id', 'tenant_id', 'action_category', 'description']);
        });
    }
};
