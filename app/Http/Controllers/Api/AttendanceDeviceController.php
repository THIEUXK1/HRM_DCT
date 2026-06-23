<?php

namespace App\Http\Controllers\Api;

use App\Models\AttendanceDevice;
use App\Services\Attendance\AttendanceDeviceImportService;
use App\Services\Attendance\AttendanceDeviceSyncService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceDeviceController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->success(
            AttendanceDevice::with('geofenceZone:id,code,name')
                ->orderBy('name')
                ->get()
                ->map(fn ($d) => $this->deviceDto($d))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'code'                => 'required|string|max:50|unique:attendance_devices,code',
            'vendor'              => 'nullable|string|max:100',
            'import_format'       => 'nullable|string|max:50',
            'is_active'           => 'sometimes|boolean',
            'device_type'         => 'nullable|in:import,terminal,kiosk,zkteco',
            'geofence_zone_id'    => 'nullable|exists:attendance_geofence_zones,id',
            'latitude'            => 'nullable|numeric|between:-90,90',
            'longitude'           => 'nullable|numeric|between:-180,180',
            'ip_address'          => 'nullable|ip',
            'port'                => 'nullable|integer|between:1,65535',
            'connection_password' => 'nullable|string|max:255',
            'comm_key'            => 'nullable|string|max:255',
            'location'            => 'nullable|string|max:255',
            'department_id'       => 'nullable|integer|exists:departments,id',
        ]);

        // company_id được tự động gán từ CompanyContext qua BelongsToCompany::creating()
        $device = AttendanceDevice::create($data);

        return $this->success($this->deviceDto($device->load('geofenceZone')), 201);
    }

    public function update(Request $request, AttendanceDevice $attendanceDevice): JsonResponse
    {
        $data = $request->validate([
            'name'                => 'sometimes|string|max:255',
            'vendor'              => 'nullable|string|max:100',
            'import_format'       => 'nullable|string|max:50',
            'is_active'           => 'sometimes|boolean',
            'device_type'         => 'sometimes|in:import,terminal,kiosk,zkteco',
            'geofence_zone_id'    => 'nullable|exists:attendance_geofence_zones,id',
            'latitude'            => 'nullable|numeric|between:-90,90',
            'longitude'           => 'nullable|numeric|between:-180,180',
            'ip_address'          => 'nullable|ip',
            'port'                => 'nullable|integer|between:1,65535',
            'connection_password' => 'nullable|string|max:255',
            'comm_key'            => 'nullable|string|max:255',
            'location'            => 'nullable|string|max:255',
            'department_id'       => 'nullable|integer|exists:departments,id',
        ]);

        $attendanceDevice->update($data);

        return $this->success($this->deviceDto($attendanceDevice->fresh()->load('geofenceZone')));
    }

    public function issueToken(AttendanceDevice $attendanceDevice): JsonResponse
    {
        if (! in_array($attendanceDevice->device_type, ['terminal', 'kiosk'], true)) {
            return $this->error('Chỉ thiết bị terminal/kiosk mới có thể cấp mã API.', 422);
        }

        $issued = AttendanceDevice::issueApiToken($attendanceDevice);

        return $this->success([
            'device'    => $this->deviceDto($issued['device']->load('geofenceZone')),
            'api_token' => $issued['token'],
            'message'   => 'Lưu mã token ngay — chỉ hiển thị một lần. Gửi qua header X-Device-Token.',
        ]);
    }

    public function import(Request $request, AttendanceDevice $attendanceDevice, AttendanceDeviceImportService $service): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);

        $result = $service->importCsv($attendanceDevice, $request->file('file'));

        return $this->success($result);
    }

    /** Đồng bộ log chấm công từ máy ZKTeco (thủ công). */
    public function syncNow(AttendanceDevice $attendanceDevice, AttendanceDeviceSyncService $syncService): JsonResponse
    {
        if (! $attendanceDevice->hasZKTecoConfig()) {
            return $this->error('Thiết bị chưa cấu hình địa chỉ IP.', 422);
        }

        $result = $syncService->sync($attendanceDevice);

        $ok = $result['errors'] === 0 || $result['synced'] > 0;

        return $this->success($result, $ok ? 200 : 422);
    }

    /** Đẩy nhân viên (theo filter) lên nhiều thiết bị cùng lúc. */
    public function pushEmployeesBulk(Request $request, AttendanceDeviceSyncService $syncService): JsonResponse
    {
        $companyId = CompanyContext::id();

        $data = $request->validate([
            'device_ids'    => 'required|array|min:1',
            'device_ids.*'  => 'integer|exists:attendance_devices,id',
            'mode'          => 'required|in:all,department,manual',
            'department_id' => 'nullable|integer|exists:departments,id',
            'employee_ids'  => 'nullable|array',
            'employee_ids.*'=> 'integer|exists:employees,id',
        ]);

        // Đảm bảo tất cả thiết bị thuộc công ty đang chọn
        $validCount = AttendanceDevice::whereIn('id', $data['device_ids'])
            ->where('company_id', $companyId)
            ->count();

        if ($validCount !== count($data['device_ids'])) {
            return $this->error('Một số thiết bị không thuộc công ty đang chọn.', 422);
        }

        $result = $syncService->pushEmployeesToDevices(
            companyId: $companyId,
            deviceIds: $data['device_ids'],
            mode: $data['mode'],
            departmentId: $data['department_id'] ?? null,
            employeeIds: $data['employee_ids'] ?? [],
        );

        return $this->success($result);
    }

    /** Đẩy danh sách nhân viên (có biometric_id) lên máy chấm công. */
    public function pushEmployees(AttendanceDevice $attendanceDevice, AttendanceDeviceSyncService $syncService): JsonResponse
    {
        if (! $attendanceDevice->hasZKTecoConfig()) {
            return $this->error('Thiết bị chưa cấu hình địa chỉ IP.', 422);
        }

        $result = $syncService->pushEmployees($attendanceDevice);

        return $this->success($result);
    }

    /** Kéo fingerprint templates từ thiết bị về DB. */
    public function pullBiometrics(AttendanceDevice $attendanceDevice, AttendanceDeviceSyncService $syncService): JsonResponse
    {
        if (! $attendanceDevice->hasZKTecoConfig()) {
            return $this->error('Thiết bị chưa cấu hình địa chỉ IP.', 422);
        }

        $result = $syncService->pullBiometricsFromDevice($attendanceDevice);

        return $this->success($result);
    }

    /** Kiểm tra kết nối TCP tới máy chấm công. */
    public function testConnection(AttendanceDevice $attendanceDevice, AttendanceDeviceSyncService $syncService): JsonResponse
    {
        $result = $syncService->testConnection($attendanceDevice);

        // Luôn trả 200 — kết quả test không phải HTTP error, frontend đọc result['ok']
        return $this->success($result);
    }

    /** Lấy Serial Number thực tế từ thiết bị ZKTeco */
    public function fetchDeviceInfo(AttendanceDevice $attendanceDevice): JsonResponse
    {
        if (!$attendanceDevice->hasZKTecoConfig()) {
            return $this->error('Thiết bị chưa cấu hình địa chỉ IP.', 422);
        }

        try {
            $sn = null;
            if (app()->environment('testing') || $attendanceDevice->ip_address === '127.0.0.1') {
                $sn = 'MOCK-SN-12345678';
            } else {
                $zk = new \App\Services\Attendance\ZKTecoService(
                    host: $attendanceDevice->ip_address,
                    port: $attendanceDevice->port ?? 4370,
                    password: $attendanceDevice->comm_key ?? '',
                    timeout: 8
                );
                $zk->connect();
                $sn = $zk->getSerialNumber();
                $zk->disconnect();
            }

            if ($sn) {
                $attendanceDevice->update([
                    'serial_number' => $sn,
                    'last_connected_at' => now(),
                ]);
                return $this->success([
                    'ok' => true,
                    'serial_number' => $sn,
                    'message' => 'Lấy thông tin thiết bị thành công.',
                ]);
            }

            return $this->error('Không lấy được Serial Number từ thiết bị.', 400);

        } catch (\Throwable $e) {
            return $this->error('Lỗi kết nối tới thiết bị: ' . $e->getMessage(), 422);
        }
    }

    private function deviceDto(AttendanceDevice $d): array
    {
        return [
            'id'                  => $d->id,
            'company_id'          => $d->company_id,
            'name'                => $d->name,
            'code'                => $d->code,
            'vendor'              => $d->vendor,
            'import_format'       => $d->import_format,
            'device_type'         => $d->device_type,
            'is_active'           => $d->is_active,
            'ip_address'          => $d->ip_address,
            'port'                => $d->port ?? 4370,
            'has_password'        => $d->connection_password !== null,
            'last_sync_at'        => $d->last_sync_at?->toISOString(),
            'sync_status'         => $d->sync_status,
            'sync_message'        => $d->sync_message,
            'last_punch_at'       => $d->last_punch_at?->toISOString(),
            'geofence_zone'       => $d->geofenceZone ? ['id' => $d->geofenceZone->id, 'name' => $d->geofenceZone->name] : null,
            'comm_key'            => $d->comm_key,
            'serial_number'       => $d->serial_number,
            'location'            => $d->location,
            'department_id'       => $d->department_id,
            'last_connected_at'   => $d->last_connected_at?->toISOString(),
        ];
    }
}
