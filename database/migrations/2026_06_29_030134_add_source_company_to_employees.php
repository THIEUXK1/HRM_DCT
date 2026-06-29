<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Nguồn đồng bộ từ hệ thống ngoài: BPVN | PFVN | MEGA | null (thủ công)
            $table->string('source_company', 20)->nullable()->after('full_name_raw');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('source_company');
        });
    }
};
