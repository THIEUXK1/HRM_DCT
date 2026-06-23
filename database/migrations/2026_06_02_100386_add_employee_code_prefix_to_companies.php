<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Prefix ký tự đầu của EMPNO để tự động định tuyến NV về đúng công ty khi sync EHR
            $table->string('employee_code_prefix', 10)->nullable()->unique()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('employee_code_prefix');
        });
    }
};
