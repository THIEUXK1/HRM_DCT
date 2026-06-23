<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_social_insurance')->default(true);
            $table->boolean('is_probation')->default(false);
            $table->integer('default_duration_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Tự động chèn 5 loại hợp đồng mặc định nhằm tương thích ngược
        $now = now();
        DB::table('contract_types')->insert([
            [
                'code' => 'indefinite',
                'name' => 'Không xác định thời hạn',
                'is_social_insurance' => true,
                'is_probation' => false,
                'default_duration_months' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'definite',
                'name' => 'Xác định thời hạn',
                'is_social_insurance' => true,
                'is_probation' => false,
                'default_duration_months' => 12,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'seasonal',
                'name' => 'Theo mùa vụ (< 12 tháng)',
                'is_social_insurance' => true,
                'is_probation' => false,
                'default_duration_months' => 6,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'probation',
                'name' => 'Thử việc',
                'is_social_insurance' => false,
                'is_probation' => true,
                'default_duration_months' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'service',
                'name' => 'Hợp đồng dịch vụ / cộng tác',
                'is_social_insurance' => false,
                'is_probation' => false,
                'default_duration_months' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_types');
    }
};
