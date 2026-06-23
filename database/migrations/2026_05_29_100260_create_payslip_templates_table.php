<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mẫu phiếu lương — Phase 1: seed BPVN-AC-PR-006, cấu hình mặc định theo công ty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslip_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('blade_view');
            $table->string('doc_code', 64)->nullable();
            $table->boolean('is_bilingual')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('payslip_templates')->insert([
            [
                'code' => 'bpvn-ac-pr-006',
                'name' => 'Phiếu lương BestPacific (BPVN-AC-PR-006)',
                'blade_view' => 'payslips.templates.bpvn-ac-pr-006',
                'doc_code' => 'BPVN-AC-PR-006 A/1',
                'is_bilingual' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'simple',
                'name' => 'Phiếu lương đơn giản',
                'blade_view' => 'payslips.show',
                'doc_code' => null,
                'is_bilingual' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $companyIds = DB::table('companies')->pluck('id');
        foreach ($companyIds as $companyId) {
            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $companyId, 'key' => 'payslip_template_code'],
                ['value' => 'bpvn-ac-pr-006', 'updated_at' => $now, 'created_at' => $now],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_templates');
    }
};
