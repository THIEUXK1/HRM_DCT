<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_entitlement_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->unsignedSmallInteger('annual_days')->default(12);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('leave_entitlement_group_id')
                ->nullable()
                ->after('manager_id')
                ->constrained('leave_entitlement_groups')
                ->nullOnDelete();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('leave_entitlement_group_id')
                ->nullable()
                ->after('position_id')
                ->constrained('leave_entitlement_groups')
                ->nullOnDelete();
            $table->decimal('annual_leave_days_override', 4, 1)
                ->nullable()
                ->after('leave_entitlement_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('leave_entitlement_group_id');
            $table->dropColumn('annual_leave_days_override');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('leave_entitlement_group_id');
        });

        Schema::dropIfExists('leave_entitlement_groups');
    }
};
