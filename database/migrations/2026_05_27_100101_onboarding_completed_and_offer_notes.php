<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable()->after('is_active');
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->text('letter_notes')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('letter_notes');
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed_at');
        });
    }
};
