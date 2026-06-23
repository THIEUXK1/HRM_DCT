<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->foreignId('default_company_id')->nullable()->after('employee_id')->constrained('companies')->nullOnDelete();
        });

        Schema::create('user_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_companies');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_company_id');
            $table->dropConstrainedForeignId('employee_id');
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
