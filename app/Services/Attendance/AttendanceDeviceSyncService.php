<?php

namespace App\Services\Attendance;

use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\EmployeeBiometricTemplate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceDeviceSyncService
{
    // ZKTeco status: 0=check-in, 1=check-out (2=break-out, 3=break-in, 4=OT-in, 5=OT-out)
    private const STATUS_CHECK_IN  = [0, 3, 4];
    private const STATUS_CHECK_OUT = [1, 2, 5];

    /**
     * Đồng bộ log chấm công từ 1 thiết bị.
     *
     * @return array{synced: int, skipped: int, errors: int, message: string}
     */
    public function sync(AttendanceDevice $device): array
    {
        if (! $device->ip_address) {
            return $this->fail($device, 'Thiết bị chưa cấu hình địa chỉ IP.');
        }

        $device->update(['sync_status' => 'syncing', 'sync_message' => null]);

        try {
            $zk = new ZKTecoService(
                host: $device->ip_address,
                port: $device->port ?? 4370,
                password: $device->connection_password ?? '',
                timeout: 15,
            );

            $zk->connect();
            $logs = $zk->getAttendanceLogs();
            $zk->disconnect();

            $result = $this->processLogs($device, $logs);

            $device->update([
                'last_sync_at' => now(),
                'sync_status'  => 'success',
                'sync_message' => "Đồng bộ {$result['synced']} bản ghi, bỏ qua {$result['skipped']}.",
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::warning("ZKTeco sync failed [{$device->code}]: " . $e->getMessage());
            return $this->fail($device, $e->getMessage());
        }
    }

    /**
     * Đẩy toàn bộ NV (có biometric_id) lên 1 thiết bị — dùng cho nút per-device.
     *
     * @return array{pushed: int, skipped: int, errors: int, message: string}
     */
    public function pushEmployees(AttendanceDevice $device): array
    {
        if (! $device->ip_address) {
            return ['pushed' => 0, 'skipped' => 0, 'errors' => 1, 'message' => 'Thiết bị chưa cấu hình IP.'];
        }

        $employees = $this->queryEmployeesWithBiometric($device->company_id);

        if ($employees->isEmpty()) {
            return ['pushed' => 0, 'skipped' => 0, 'errors' => 0, 'message' => 'Không có nhân viên nào có Mã máy chấm công.'];
        }

        $result = $this->doPushToDevice($device, $employees);

        return array_merge($result, ['skipped' => 0]);
    }

    /**
     * Đẩy nhân viên (theo filter) lên nhiều thiết bị cùng lúc.
     *
     * @param  int     $companyId
     * @param  int[]   $deviceIds
     * @param  string  $mode         'all' | 'department' | 'manual'
     * @param  ?int    $departmentId  (dùng khi mode=department)
     * @param  int[]   $employeeIds   (dùng khi mode=manual)
     * @return array{results: list<array{device_id: int, device_name: string, pushed: int, errors: int, message: string}>}
     */
    public function pushEmployeesToDevices(
        int $companyId,
        array $deviceIds,
        string $mode = 'all',
        ?int $departmentId = null,
        array $employeeIds = [],
    ): array {
        $devices = AttendanceDevice::whereIn('id', $deviceIds)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotNull('ip_address')
            ->get();

        if ($devices->isEmpty()) {
            return ['results' => [], 'message' => 'Không tìm thấy thiết bị hợp lệ (đang hoạt động và có IP).'];
        }

        $employees = $this->queryEmployeesWithBiometric($companyId, $mode, $departmentId, $employeeIds);

        if ($employees->isEmpty()) {
            return ['results' => [], 'message' => 'Không có nhân viên nào phù hợp với bộ lọc (cần có Mã máy chấm công).'];
        }

        $results = [];
        foreach ($devices as $device) {
            $r = $this->doPushToDevice($device, $employees);
            $results[] = array_merge($r, [
                'device_id'   => $device->id,
                'device_name' => $device->name,
            ]);
        }

        return ['results' => $results];
    }

    /**
     * Kéo toàn bộ fingerprint templates từ thiết bị về DB.
     *
     * Luồng: lấy danh sách user trên máy → khớp biometric_id → getFingerprints(uid) → lưu base64.
     *
     * @return array{pulled: int, employees: int, skipped: int, errors: int, message: string}
     */
    public function pullBiometricsFromDevice(AttendanceDevice $device): array
    {
        if (! $device->ip_address) {
            return ['pulled' => 0, 'employees' => 0, 'skipped' => 0, 'errors' => 1, 'message' => 'Thiết bị chưa cấu hình IP.'];
        }

        try {
            $zk = new ZKTecoService(
                host: $device->ip_address,
                port: $device->port ?? 4370,
                password: $device->connection_password ?? '',
                timeout: 30,
            );
            $zk->connect();

            $users    = $zk->getUsers();   // keyed by userid (PIN string)
            $bioMap   = $this->buildBiometricMap($device->company_id); // PIN => employee_id
            $pulled   = $skipped = $errors = $empCount = 0;

            foreach ($users as $pin => $userInfo) {
                $pin = trim((string) $pin);
                $employeeId = $bioMap[$pin] ?? null;

                if (! $employeeId) {
                    $skipped++;
                    continue;
                }

                $uid       = (int) $userInfo['uid'];
                $templates = $zk->getFingerprints($uid); // [finger_index => binary]

                if (empty($templates)) {
                    $skipped++;
                    continue;
                }

                $empCount++;
                foreach ($templates as $fingerIndex => $binary) {
                    try {
                        EmployeeBiometricTemplate::updateOrCreate(
                            ['employee_id' => $employeeId, 'finger_index' => $fingerIndex],
                            [
                                'company_id'       => $device->company_id,
                                'template'         => base64_encode($binary),
                                'source_device_id' => $device->id,
                                'synced_at'        => now(),
                            ],
                        );
                        $pulled++;
                    } catch (\Throwable $e) {
                        Log::warning("ZKTeco pull fingerprint failed emp={$employeeId} finger={$fingerIndex}: " . $e->getMessage());
                        $errors++;
                    }
                }
            }

            $zk->disconnect();

            return [
                'pulled'    => $pulled,
                'employees' => $empCount,
                'skipped'   => $skipped,
                'errors'    => $errors,
                'message'   => "Đã lưu {$pulled} mẫu vân tay từ {$empCount} nhân viên, bỏ qua {$skipped}, lỗi {$errors}.",
            ];
        } catch (\Throwable $e) {
            Log::warning("ZKTeco pullBiometrics failed [{$device->code}]: " . $e->getMessage());
            return ['pulled' => 0, 'employees' => 0, 'skipped' => 0, 'errors' => 1, 'message' => $e->getMessage()];
        }
    }

    /** Kiểm tra kết nối mà không lấy dữ liệu. */
    public function testConnection(AttendanceDevice $device): array
    {
        if (! $device->ip_address) {
            return ['ok' => false, 'message' => 'Chưa cấu hình địa chỉ IP.'];
        }

        try {
            $zk = new ZKTecoService(
                host: $device->ip_address,
                port: $device->port ?? 4370,
                password: $device->connection_password ?? '',
                timeout: 8,
            );
            $zk->connect();
            $zk->disconnect();
            return ['ok' => true, 'message' => 'Kết nối thành công.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------ //

    /**
     * Kết nối thiết bị và đẩy danh sách nhân viên lên (kèm vân tay nếu có).
     *
     * @return array{pushed: int, errors: int, fingerprints: int, message: string}
     */
    private function doPushToDevice(AttendanceDevice $device, Collection $employees): array
    {
        try {
            $zk = new ZKTecoService(
                host: $device->ip_address,
                port: $device->port ?? 4370,
                password: $device->connection_password ?? '',
                timeout: 30,
            );
            $zk->connect();

            // Load tất cả template vân tay cho nhân viên này (1 query, không N+1)
            $employeeIds   = $employees->pluck('id')->all();
            $fingerprintMap = EmployeeBiometricTemplate::whereIn('employee_id', $employeeIds)
                ->get()
                ->groupBy('employee_id')
                ->map(fn ($rows) => $rows->pluck('template', 'finger_index')->all());

            $pushed = $errors = $fingerprintsPushed = 0;

            foreach ($employees as $emp) {
                $pin  = (string) $emp->biometric_id;
                $name = trim("{$emp->last_name} {$emp->first_name}");
                $card = $emp->card_number ? (int) $emp->card_number : 0;
                $uid  = (int) $pin;

                try {
                    $zk->pushUser($pin, $name, $card);
                    $pushed++;

                    // Đẩy vân tay nếu có template trong DB
                    $templates = $fingerprintMap[$emp->id] ?? [];
                    if (! empty($templates)) {
                        $binary = array_map('base64_decode', $templates);
                        $fingerprintsPushed += $zk->pushFingerprints($uid, $binary);
                    }
                } catch (\Throwable $e) {
                    Log::warning("ZKTeco pushUser failed PIN={$pin}: " . $e->getMessage());
                    $errors++;
                }
            }

            $zk->disconnect();

            $msg = "Đã đẩy {$pushed} NV";
            if ($fingerprintsPushed > 0) {
                $msg .= " + {$fingerprintsPushed} mẫu vân tay";
            }
            $msg .= $errors > 0 ? ", lỗi {$errors}." : '.';

            return [
                'pushed'       => $pushed,
                'errors'       => $errors,
                'fingerprints' => $fingerprintsPushed,
                'message'      => $msg,
            ];
        } catch (\Throwable $e) {
            Log::warning("ZKTeco doPushToDevice failed [{$device->code}]: " . $e->getMessage());
            return ['pushed' => 0, 'errors' => 1, 'fingerprints' => 0, 'message' => $e->getMessage()];
        }
    }

    /**
     * Truy vấn nhân viên có biometric_id, lọc theo mode.
     *
     * Query đi từ employee_profiles (filtered by biometric_id index) → join employees.
     * Thứ tự này hiệu quả hơn khi tỷ lệ NV có biometric_id thấp hơn tổng NV.
     * Index: ep_biometric_id_idx, emp_company_dept_idx
     */
    private function queryEmployeesWithBiometric(
        int $companyId,
        string $mode = 'all',
        ?int $departmentId = null,
        array $employeeIds = [],
    ): Collection {
        $query = Employee::query()
            // Drive từ employee_profiles để tận dụng ep_biometric_id_idx
            ->join('employee_profiles', 'employees.id', '=', 'employee_profiles.employee_id')
            ->whereNotNull('employee_profiles.biometric_id')
            ->where('employee_profiles.biometric_id', '!=', '')
            // emp_company_dept_idx covering (company_id, department_id)
            ->where('employees.company_id', $companyId)
            ->select(
                'employees.id',
                'employees.first_name',
                'employees.last_name',
                'employee_profiles.biometric_id',
                'employee_profiles.card_number',
            );

        if ($mode === 'department' && $departmentId) {
            $query->where('employees.department_id', $departmentId);
        } elseif ($mode === 'manual' && ! empty($employeeIds)) {
            $query->whereIn('employees.id', $employeeIds);
        }

        return $query->get();
    }

    /**
     * @param  array<int, array{user_id: string, punched_at: Carbon, status: int, verify: int}>  $rawLogs
     * @return array{synced: int, skipped: int, errors: int, message: string}
     */
    private function processLogs(AttendanceDevice $device, array $rawLogs): array
    {
        $synced = $skipped = $errors = 0;

        $biometricMap = $this->buildBiometricMap($device->company_id);

        // Group punches by (user_id, work_date)
        $grouped = [];
        foreach ($rawLogs as $log) {
            $workDate = $this->resolveWorkDate($log['punched_at']);
            $key = $log['user_id'] . '|' . $workDate;
            $grouped[$key][] = $log;
        }

        foreach ($grouped as $key => $punches) {
            [$userId, $workDate] = explode('|', $key, 2);

            $employeeId = $biometricMap[$userId] ?? null;
            if (! $employeeId) {
                $skipped++;
                continue;
            }

            try {
                DB::transaction(function () use ($punches, $employeeId, $workDate, $device) {
                    $checkIn  = $this->earliestCheckIn($punches);
                    $checkOut = $this->latestCheckOut($punches);

                    AttendanceLog::updateOrCreate(
                        ['employee_id' => $employeeId, 'work_date' => Carbon::parse($workDate)],
                        array_filter([
                            'company_id'            => $device->company_id,
                            'attendance_device_id'  => $device->id,
                            'check_in_at'           => $checkIn,
                            'check_out_at'          => $checkOut,
                            'source'                => 'device',
                        ], fn ($v) => $v !== null),
                    );
                });
                $synced++;
            } catch (\Throwable $e) {
                Log::warning("ZKTeco log insert failed: " . $e->getMessage());
                $errors++;
            }
        }

        return [
            'synced'  => $synced,
            'skipped' => $skipped,
            'errors'  => $errors,
            'message' => "Đồng bộ {$synced} bản ghi, bỏ qua {$skipped} (không tìm thấy NV), lỗi {$errors}.",
        ];
    }

    private function earliestCheckIn(array $punches): ?Carbon
    {
        $ins = array_filter($punches, fn ($p) => in_array($p['status'], self::STATUS_CHECK_IN, true));
        if (! $ins) {
            return null;
        }
        return collect($ins)->sortBy('punched_at')->first()['punched_at'];
    }

    private function latestCheckOut(array $punches): ?Carbon
    {
        $outs = array_filter($punches, fn ($p) => in_array($p['status'], self::STATUS_CHECK_OUT, true));
        if (! $outs) {
            return null;
        }
        return collect($outs)->sortByDesc('punched_at')->first()['punched_at'];
    }

    private function resolveWorkDate(Carbon $punchedAt): string
    {
        if ($punchedAt->hour < 6) {
            return $punchedAt->copy()->subDay()->toDateString();
        }
        return $punchedAt->toDateString();
    }

    /**
     * @return array<string, int>  biometric_id => employee_id
     * Index: ep_biometric_id_idx → employees PK
     */
    private function buildBiometricMap(int $companyId): array
    {
        return Employee::query()
            ->join('employee_profiles', 'employees.id', '=', 'employee_profiles.employee_id')
            ->whereNotNull('employee_profiles.biometric_id')
            ->where('employee_profiles.biometric_id', '!=', '')
            ->where('employees.company_id', $companyId)
            ->pluck('employees.id', 'employee_profiles.biometric_id')
            ->all();
    }

    private function fail(AttendanceDevice $device, string $message): array
    {
        $device->update([
            'last_sync_at' => now(),
            'sync_status'  => 'failed',
            'sync_message' => $message,
        ]);

        return ['synced' => 0, 'skipped' => 0, 'errors' => 1, 'message' => $message];
    }
}
