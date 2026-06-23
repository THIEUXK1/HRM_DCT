<?php

namespace App\Services\Attendance;

use App\Models\AttendanceDevice;
use App\Models\AttendanceGeofenceZone;
use App\Models\AttendanceLog;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Company\CompanyPolicyResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AttendancePunchService
{
    public function __construct(
        private readonly GeofenceService $geofence,
        private readonly AttendancePunchFraudGuard $fraudGuard,
    ) {}

    /**
     * @return array{punch: AttendancePunch, log: AttendanceLog, message: string}
     */
    public function punch(
        Employee $employee,
        string $punchType,
        string $source,
        ?float $latitude = null,
        ?float $longitude = null,
        ?int $accuracyMeters = null,
        ?AttendanceDevice $device = null,
        ?string $ipAddress = null,
        ?string $zoneCode = null,
        ?string $gateToken = null,
    ): array {
        if (! in_array($punchType, ['in', 'out'], true)) {
            throw new RuntimeException('Loại chấm công không hợp lệ.');
        }

        $this->assertMobileEnabled($employee->company_id, $source);

        $user = auth()->user();
        if ($user) {
            $this->fraudGuard->assertPunchPermission($user, $source);
        }

        $this->fraudGuard->assertBeforePunch(
            $employee,
            $punchType,
            $source,
            $latitude,
            $longitude,
            $accuracyMeters,
        );

        $now = now();
        $validation = $this->validateLocation(
            $employee,
            $source,
            $latitude,
            $longitude,
            $device,
            $now,
            $zoneCode,
            $gateToken,
        );

        if (! $validation['is_valid'] && $this->isStrictGeofence($employee->company_id)) {
            throw new RuntimeException($validation['message'] ?? 'Vị trí chấm công không hợp lệ.');
        }

        $logSource = match ($validation['location_status']) {
            'field_trip' => 'field',
            'device_trusted' => 'device',
            'qr_gate' => 'qr',
            default => in_array($source, ['kiosk', 'qr'], true) ? $source : 'mobile',
        };

        return DB::transaction(function () use (
            $employee, $punchType, $source, $latitude, $longitude,
            $accuracyMeters, $device, $ipAddress, $now, $validation, $logSource
        ) {
            $punch = AttendancePunch::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'attendance_device_id' => $device?->id,
                'geofence_zone_id' => $validation['zone']?->id,
                'punch_type' => $punchType,
                'source' => $logSource === 'qr' ? 'qr' : ($device ? 'device' : $source),
                'punched_at' => $now,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy_meters' => $accuracyMeters,
                'is_valid' => $validation['is_valid'],
                'validation_message' => $validation['message'],
                'ip_address' => $ipAddress,
            ]);

            $log = $this->applyToDailyLog(
                $employee, $punchType, $now, $latitude, $longitude, $validation, $device, $logSource
            );

            if ($device) {
                $device->update(['last_punch_at' => $now]);
            }

            return [
                'punch' => $punch->load('zone'),
                'log' => $log,
                'message' => $validation['message'] ?? 'Chấm công thành công.',
            ];
        });
    }

    /** @return array{today: ?AttendanceLog, last_punches: \Illuminate\Support\Collection, zones: \Illuminate\Support\Collection} */
    public function todayStatus(Employee $employee): array
    {
        $employee->loadMissing('branch:id,name,code');
        $today = now()->toDateString();

        return [
            'today' => AttendanceLog::where('employee_id', $employee->id)
                ->whereDate('work_date', $today)
                ->first(),
            'last_punches' => AttendancePunch::with('zone:id,name,code')
                ->where('employee_id', $employee->id)
                ->whereDate('punched_at', $today)
                ->orderByDesc('punched_at')
                ->limit(10)
                ->get(),
            'zones' => $this->geofence->zonesForEmployee($employee)
                ->sortBy('name')
                ->values()
                ->map(fn (AttendanceGeofenceZone $z) => $z->only([
                    'id', 'code', 'name', 'zone_type', 'latitude', 'longitude', 'radius_meters', 'branch_id',
                ])),
            'branch' => $employee->branch?->only(['id', 'name', 'code']),
        ];
    }

    /** @return array{is_valid: bool, message: ?string, zone: ?AttendanceGeofenceZone, location_status: string} */
    private function validateLocation(
        Employee $employee,
        string $source,
        ?float $latitude,
        ?float $longitude,
        ?AttendanceDevice $device,
        Carbon $at,
        ?string $zoneCode = null,
        ?string $gateToken = null,
    ): array {
        if ($source === 'device' && $device) {
            return $this->validateDevice($device, $latitude, $longitude);
        }

        if ($zoneCode && $gateToken) {
            $qrValidation = $this->validateQrGate($employee, $zoneCode, $gateToken);
            if ($qrValidation !== null) {
                return $qrValidation;
            }
        }

        if ($latitude === null || $longitude === null) {
            return [
                'is_valid' => false,
                'message' => 'Thiếu tọa độ GPS hoặc mã QR cổng hợp lệ.',
                'zone' => null,
                'location_status' => 'outside',
            ];
        }

        if ($this->hasApprovedFieldTrip($employee, $at)) {
            $fieldZone = $this->geofence->bestMatchForEmployee($employee, $latitude, $longitude, $source);

            return [
                'is_valid' => true,
                'message' => 'Chấm công công tác (đơn công tác đã duyệt).',
                'zone' => $fieldZone,
                'location_status' => 'field_trip',
            ];
        }

        $zone = $this->geofence->bestMatchForEmployee($employee, $latitude, $longitude, $source);
        if ($zone) {
            $branchNote = $zone->branch_id ? '' : ' (vùng chung công ty)';

            return [
                'is_valid' => true,
                'message' => 'Trong phạm vi chi nhánh: '.$zone->name.$branchNote,
                'zone' => $zone,
                'location_status' => 'valid',
            ];
        }

        $anyZone = $this->geofence->bestMatch($employee->company_id, $latitude, $longitude, $source);
        if ($anyZone && ! $this->geofence->zoneAllowedForEmployee($anyZone, $employee)) {
            return [
                'is_valid' => false,
                'message' => 'Vị trí thuộc chi nhánh khác. Bạn chỉ được chấm công tại chi nhánh được phân công.',
                'zone' => null,
                'location_status' => 'outside',
            ];
        }

        return [
            'is_valid' => false,
            'message' => 'Bạn đang ngoài phạm vi chi nhánh được phép chấm công.',
            'zone' => null,
            'location_status' => 'outside',
        ];
    }

    /** @return array{is_valid: bool, message: string, zone: ?AttendanceGeofenceZone, location_status: string} */
    private function validateDevice(
        AttendanceDevice $device,
        ?float $latitude,
        ?float $longitude,
    ): array {
        if ($device->geofence_zone_id && $latitude !== null && $longitude !== null) {
            $zone = AttendanceGeofenceZone::find($device->geofence_zone_id);
            if ($zone && ! $this->geofence->isInsideZone($zone, $latitude, $longitude)) {
                return [
                    'is_valid' => false,
                    'message' => 'Máy chấm công ngoài phạm vi khu vực đã gán.',
                    'zone' => null,
                    'location_status' => 'outside',
                ];
            }

            return [
                'is_valid' => true,
                'message' => 'Chấm qua máy: '.$device->name,
                'zone' => $zone ?? null,
                'location_status' => 'device_trusted',
            ];
        }

        return [
            'is_valid' => true,
            'message' => 'Chấm qua máy tin cậy: '.$device->name,
            'zone' => $device->geofenceZone,
            'location_status' => 'device_trusted',
        ];
    }

    /** @return array{is_valid: bool, message: string, zone: ?AttendanceGeofenceZone, location_status: string}|null */
    private function validateQrGate(Employee $employee, string $zoneCode, string $gateToken): ?array
    {
        $zone = AttendanceGeofenceZone::query()
            ->where('company_id', $employee->company_id)
            ->where('code', $zoneCode)
            ->where('is_active', true)
            ->first();

        if (! $zone || ! $zone->gate_token_hash) {
            return [
                'is_valid' => false,
                'message' => 'Mã QR cổng không hợp lệ hoặc chưa được HR cấp.',
                'zone' => null,
                'location_status' => 'outside',
            ];
        }

        if (! hash_equals($zone->gate_token_hash, hash('sha256', $gateToken))) {
            return [
                'is_valid' => false,
                'message' => 'Mã QR cổng không đúng. Vui lòng quét lại tại cổng.',
                'zone' => null,
                'location_status' => 'outside',
            ];
        }

        if ($zone->branch_id && ! $employee->branch_id) {
            return [
                'is_valid' => false,
                'message' => 'Tài khoản chưa được gán chi nhánh làm việc. Liên hệ HR.',
                'zone' => null,
                'location_status' => 'outside',
            ];
        }

        if (! $this->geofence->zoneAllowedForEmployee($zone, $employee)) {
            return [
                'is_valid' => false,
                'message' => 'QR cổng không thuộc chi nhánh làm việc của bạn.',
                'zone' => null,
                'location_status' => 'outside',
            ];
        }

        if (! $zone->allowsSource('qr') && ! $zone->allowsSource('mobile')) {
            return [
                'is_valid' => false,
                'message' => 'Khu vực này chưa bật chấm công qua QR.',
                'zone' => null,
                'location_status' => 'outside',
            ];
        }

        return [
            'is_valid' => true,
            'message' => 'Quét QR cổng: '.$zone->name,
            'zone' => $zone,
            'location_status' => 'qr_gate',
        ];
    }

    private function applyToDailyLog(
        Employee $employee,
        string $punchType,
        Carbon $at,
        ?float $latitude,
        ?float $longitude,
        array $validation,
        ?AttendanceDevice $device,
        string $logSource,
    ): AttendanceLog {
        $workDate = $at->toDateString();

        $log = AttendanceLog::firstOrNew([
            'employee_id' => $employee->id,
            'work_date' => $workDate,
        ]);

        $log->company_id = $employee->company_id;
        $log->attendance_device_id = $device?->id ?? $log->attendance_device_id;
        $log->source = $logSource;
        $log->location_status = $validation['location_status'];

        if ($punchType === 'in') {
            if (! $log->check_in_at) {
                $log->check_in_at = $at;
            }
            $log->check_in_latitude = $latitude;
            $log->check_in_longitude = $longitude;
            $log->check_in_zone_id = $validation['zone']?->id;
        } else {
            $log->check_out_at = $at;
            $log->check_out_latitude = $latitude;
            $log->check_out_longitude = $longitude;
            $log->check_out_zone_id = $validation['zone']?->id;
        }

        $log->save();

        return $log->fresh();
    }

    private function hasApprovedFieldTrip(Employee $employee, Carbon $date): bool
    {
        $tripCode = CompanyPolicyResolver::for($employee->company_id)
            ->getString('attendance_field_trip_code', 'CONG_TAC');

        $leaveTypeId = LeaveType::where('company_id', $employee->company_id)
            ->where('code', $tripCode)
            ->value('id');

        if (! $leaveTypeId) {
            return false;
        }

        return LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveTypeId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->exists();
    }

    private function assertMobileEnabled(int $companyId, string $source): void
    {
        if (! in_array($source, ['mobile', 'field', 'kiosk', 'qr'], true)) {
            return;
        }

        if (! CompanyPolicyResolver::for($companyId)->getBool('attendance_mobile_punch_enabled', true)) {
            throw new RuntimeException('Chấm công qua điện thoại chưa được bật cho công ty.');
        }
    }

    private function isStrictGeofence(int $companyId): bool
    {
        return CompanyPolicyResolver::for($companyId)->getBool('attendance_geofence_strict', true);
    }
}
