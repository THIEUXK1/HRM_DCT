<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chấm công đa kênh: GPS geofence, mobile, máy chấm công, thiết bị công tác.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_geofence_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('zone_type', 32)->default('factory')
                ->comment('factory|office|warehouse|field_site');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters')->default(200);
            $table->json('allowed_sources')->nullable()
                ->comment('mobile,device,kiosk');
            $table->boolean('is_active')->default(true);
            $table->text('address_note')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->string('device_type', 32)->default('import')->after('vendor')
                ->comment('import|terminal|kiosk');
            $table->string('api_token_hash', 64)->nullable()->after('device_type');
            $table->foreignId('geofence_zone_id')->nullable()->after('api_token_hash')
                ->constrained('attendance_geofence_zones')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable()->after('geofence_zone_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->timestamp('last_punch_at')->nullable()->after('longitude');
        });

        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('geofence_zone_id')->nullable()->constrained('attendance_geofence_zones')->nullOnDelete();
            $table->string('punch_type', 8); // in|out
            $table->string('source', 32); // mobile|device|kiosk|field
            $table->dateTime('punched_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('accuracy_meters')->nullable();
            $table->boolean('is_valid')->default(true);
            $table->string('validation_message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'punched_at']);
        });

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->decimal('check_in_latitude', 10, 7)->nullable()->after('check_out_at');
            $table->decimal('check_in_longitude', 10, 7)->nullable()->after('check_in_latitude');
            $table->decimal('check_out_latitude', 10, 7)->nullable()->after('check_in_longitude');
            $table->decimal('check_out_longitude', 10, 7)->nullable()->after('check_out_latitude');
            $table->foreignId('check_in_zone_id')->nullable()->after('check_out_longitude')
                ->constrained('attendance_geofence_zones')->nullOnDelete();
            $table->foreignId('check_out_zone_id')->nullable()->after('check_in_zone_id')
                ->constrained('attendance_geofence_zones')->nullOnDelete();
            $table->string('location_status', 32)->nullable()->after('check_out_zone_id')
                ->comment('valid|outside|field_trip|device_trusted');
        });

        $this->seedDemoZones();
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('check_in_zone_id');
            $table->dropConstrainedForeignId('check_out_zone_id');
            $table->dropColumn([
                'check_in_latitude', 'check_in_longitude',
                'check_out_latitude', 'check_out_longitude',
                'location_status',
            ]);
        });
        Schema::dropIfExists('attendance_punches');
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('geofence_zone_id');
            $table->dropColumn(['device_type', 'api_token_hash', 'latitude', 'longitude', 'last_punch_at']);
        });
        Schema::dropIfExists('attendance_geofence_zones');
    }

    private function seedDemoZones(): void
    {
        $now = now();
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            DB::table('attendance_geofence_zones')->insert([
                [
                    'company_id' => $companyId,
                    'code' => 'NM-MAIN',
                    'name' => 'Nhà máy chính (Demo GPS)',
                    'zone_type' => 'factory',
                    'latitude' => 10.776889,
                    'longitude' => 106.700806,
                    'radius_meters' => 350,
                    'allowed_sources' => json_encode(['mobile', 'device', 'kiosk']),
                    'is_active' => true,
                    'address_note' => 'Quận 1, TP.HCM — chỉnh tọa độ thực tế trong Settings',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $companyId,
                    'code' => 'VP-HC',
                    'name' => 'Văn phòng hành chính',
                    'zone_type' => 'office',
                    'latitude' => 10.782000,
                    'longitude' => 106.695000,
                    'radius_meters' => 150,
                    'allowed_sources' => json_encode(['mobile', 'kiosk']),
                    'is_active' => true,
                    'address_note' => 'Khu vực VP — demo',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            foreach ([
                'attendance_mobile_punch_enabled' => '1',
                'attendance_geofence_strict' => '1',
                'attendance_field_trip_code' => 'CONG_TAC',
            ] as $key => $value) {
                DB::table('company_settings')->updateOrInsert(
                    ['company_id' => $companyId, 'key' => $key],
                    ['value' => $value, 'created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }
};
