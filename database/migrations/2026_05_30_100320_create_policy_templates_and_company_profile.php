<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gói chính sách nhân sự theo công ty (dệt / may / kinh doanh).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('industry_code', 32);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('policy_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_template_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 32);
            $table->string('item_key', 128);
            $table->json('value_json');
            $table->timestamps();

            $table->unique(['policy_template_id', 'domain', 'item_key']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('industry_code', 32)->nullable()->after('code');
            $table->string('policy_template_code', 32)->nullable()->after('industry_code');
            $table->timestamp('policy_applied_at')->nullable()->after('policy_template_code');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['industry_code', 'policy_template_code', 'policy_applied_at']);
        });

        Schema::dropIfExists('policy_template_items');
        Schema::dropIfExists('policy_templates');
    }
};
