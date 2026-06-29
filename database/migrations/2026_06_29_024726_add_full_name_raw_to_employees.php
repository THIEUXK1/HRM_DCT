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
            // Tên gốc chưa tách từ API ngoài, ví dụ: "Lê Đình Triển 黎廷展"
            $table->string('full_name_raw')->nullable()->after('chinese_name');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('full_name_raw');
        });
    }
};
