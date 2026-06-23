<?php

namespace App\Http\Controllers\Api;

use App\Models\AttendancePunch;
use App\Models\AttendanceRawLog;
use App\Models\AttendanceSource;
use App\Models\AttendanceSyncLog;
use App\Models\EmployeeAttendanceMapping;
use App\Services\Attendance\ZKTimeSyncService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceSourceController extends ApiController
{
    public function __construct()
    {
        // Require role permissions (handled in routing, but enforce just in case)
        $this->middleware('role_or_permission:admin|attendance.manage');
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->query('company_id');
        $query = AttendanceSource::with('company:id,name');
        
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $this->success($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:32',
            'host' => 'required|string|max:255',
            'port' => 'nullable|integer',
            'database_name' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'password_encrypted' => 'required|string',
            'timezone' => 'nullable|string|max:64',
            'user_table' => 'nullable|string|max:64',
            'checkinout_table' => 'nullable|string|max:64',
            'employee_code_field' => 'nullable|string|max:64',
            'badge_field' => 'nullable|string|max:64',
            'check_time_field' => 'nullable|string|max:64',
            'is_active' => 'nullable|boolean',
            'sync_time' => 'nullable|string|max:5',
        ]);

        $source = AttendanceSource::create($data);

        AuditLogger::log('attendance_source_created', $source, null, 'attendance', "Tạo nguồn chấm công ZKTime: {$source->name}");

        return $this->success($source, 201);
    }

    public function show(AttendanceSource $attendanceSource): JsonResponse
    {
        return $this->success($attendanceSource);
    }

    public function update(Request $request, AttendanceSource $attendanceSource): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:32',
            'host' => 'required|string|max:255',
            'port' => 'nullable|integer',
            'database_name' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'password_encrypted' => 'nullable|string',
            'timezone' => 'nullable|string|max:64',
            'user_table' => 'nullable|string|max:64',
            'checkinout_table' => 'nullable|string|max:64',
            'employee_code_field' => 'nullable|string|max:64',
            'badge_field' => 'nullable|string|max:64',
            'check_time_field' => 'nullable|string|max:64',
            'is_active' => 'nullable|boolean',
            'sync_time' => 'nullable|string|max:5',
        ]);

        if (empty($data['password_encrypted'])) {
            unset($data['password_encrypted']); // Do not overwrite with empty password
        }

        $attendanceSource->update($data);

        AuditLogger::log('attendance_source_updated', $attendanceSource, null, 'attendance', "Cập nhật nguồn chấm công ZKTime: {$attendanceSource->name}");

        return $this->success($attendanceSource);
    }

    public function destroy(AttendanceSource $attendanceSource): JsonResponse
    {
        $attendanceSource->delete();

        AuditLogger::log('attendance_source_deleted', $attendanceSource, null, 'attendance', "Xóa nguồn chấm công ZKTime: {$attendanceSource->name}");

        return $this->noContent();
    }

    public function testConnection(AttendanceSource $attendanceSource, ZKTimeSyncService $syncService): JsonResponse
    {
        $result = $syncService->testConnection($attendanceSource);

        AuditLogger::log(
            'attendance_source_tested',
            $attendanceSource,
            null,
            'attendance',
            "Kiểm tra kết nối nguồn ZKTime: {$attendanceSource->name} - Kết quả: " . ($result['ok'] ? 'Thành công' : 'Thất bại')
        );

        return $this->success($result);
    }

    public function syncNow(Request $request, AttendanceSource $attendanceSource, ZKTimeSyncService $syncService): JsonResponse
    {
        $data = $request->validate([
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d',
            'dry_run' => 'nullable|boolean',
        ]);

        $dryRun = (bool) ($data['dry_run'] ?? false);

        $result = $syncService->sync($attendanceSource, $data['from'], $data['to'], $dryRun);

        AuditLogger::log(
            'attendance_source_synced',
            $attendanceSource,
            null,
            'attendance',
            "Đồng bộ nguồn ZKTime: {$attendanceSource->name} từ {$data['from']} đến {$data['to']} (" . ($dryRun ? 'Chạy thử' : 'Chạy thật') . ")"
        );

        return $this->success($result);
    }

    public function syncLogs(AttendanceSource $attendanceSource): JsonResponse
    {
        $logs = AttendanceSyncLog::where('attendance_source_id', $attendanceSource->id)
            ->orderByDesc('started_at')
            ->limit(100)
            ->get();

        return $this->success($logs);
    }

    public function unmappedLogs(AttendanceSource $attendanceSource): JsonResponse
    {
        $logs = AttendanceRawLog::where('attendance_source_id', $attendanceSource->id)
            ->where('status', 'unmapped')
            ->orderByDesc('check_time')
            ->limit(100)
            ->get();

        return $this->success($logs);
    }

    public function saveMapping(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'employee_id' => 'required|exists:employees,id',
            'employee_code' => 'required|string',
            'device_user_id' => 'required|string',
        ]);

        $mapping = EmployeeAttendanceMapping::updateOrCreate(
            ['company_id' => $data['company_id'], 'employee_id' => $data['employee_id']],
            [
                'employee_code' => $data['employee_code'],
                'device_user_id' => $data['device_user_id']
            ]
        );

        // Re-process any unmapped logs for this mapping
        $unmappedLogs = AttendanceRawLog::where('company_id', $data['company_id'])
            ->where('device_user_id', $data['device_user_id'])
            ->where('status', 'unmapped')
            ->get();

        $resolvedCount = 0;
        if ($unmappedLogs->isNotEmpty()) {
            DB::transaction(function () use ($unmappedLogs, $mapping, &$resolvedCount) {
                foreach ($unmappedLogs as $rawLog) {
                    $rawLog->update([
                        'employee_id' => $mapping->employee_id,
                        'employee_code' => $mapping->employee_code,
                        'status' => 'processed',
                    ]);

                    $payload = $rawLog->raw_payload;
                    $checkType = $payload['check_type'] ?? $payload['CHECKTYPE'] ?? 'I';
                    $checkType = trim(strtoupper((string) $checkType));
                    $punchType = in_array($checkType, ['O', '1', '2', '5', 'OUT'], true) ? 'out' : 'in';

                    AttendancePunch::firstOrCreate([
                        'company_id' => $rawLog->company_id,
                        'employee_id' => $mapping->employee_id,
                        'punched_at' => $rawLog->check_time->toDateTimeString(),
                    ], [
                        'punch_type' => $punchType,
                        'source' => 'device',
                        'is_valid' => true,
                        'validation_message' => 'Đồng bộ lại sau khi ánh xạ nhân viên.',
                    ]);

                    $resolvedCount++;
                }
            });
        }

        AuditLogger::log('employee_attendance_mapping_saved', $mapping, null, 'attendance', "Lưu ánh xạ nhân viên: Code {$mapping->employee_code} với Device ID {$mapping->device_user_id}");

        return $this->success([
            'mapping' => $mapping,
            'resolved_logs' => $resolvedCount
        ]);
    }

    public function syncBadgeNumbers(Request $request, AttendanceSource $attendanceSource, ZKTimeSyncService $syncService): JsonResponse
    {
        $data = $request->validate([
            'dry_run' => 'nullable|boolean',
            'force' => 'nullable|boolean',
        ]);

        $dryRun = (bool) ($data['dry_run'] ?? false);
        $force = (bool) ($data['force'] ?? false);

        $result = $syncService->syncFingerprintCodes($attendanceSource, $dryRun, $force);

        AuditLogger::log(
            'zktime_sync_badge_numbers',
            $attendanceSource,
            null,
            'attendance',
            "Đồng bộ Mã vân tay từ nguồn ZKTime: {$attendanceSource->name} (" . ($dryRun ? 'Chạy thử' : 'Chạy thật') . ", Force: " . ($force ? 'Có' : 'Không') . ")"
        );

        return $this->success($result);
    }
}
