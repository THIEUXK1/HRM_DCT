<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_holidays', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'holiday_date']);
        });

        Schema::table('company_holidays', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('holiday_date');
        });

        DB::table('company_holidays')->update([
            'end_date' => DB::raw('holiday_date'),
        ]);

        Schema::table('company_holidays', function (Blueprint $table) {
            $table->index(['company_id', 'holiday_date']);
        });
    }

    public function down(): void
    {
        Schema::table('company_holidays', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'holiday_date']);
            $table->dropColumn('end_date');
            $table->unique(['company_id', 'holiday_date']);
        });
    }
};
