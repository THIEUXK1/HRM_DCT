<?php

namespace App\Services\Attendance;

use App\Models\AttendanceLog;
use App\Models\AttendancePunch;
use App\Models\AttendanceRawLog;
use App\Models\AttendanceSource;
use App\Models\AttendanceSyncLog;
use App\Models\Employee;
use App\Models\EmployeeAttendanceMapping;
use App\Models\EmployeeProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ZKTimeSyncService
{
    public function __construct(
        private readonly ZKTimeConnectionFactory $connectionFactory,
    ) {}

    /**
     * Tests connection to the database and checks for required tables.
     *
     * @param AttendanceSource $source
     * @return array{ok: bool, message: string, user_count?: int, log_count?: int}
     */
    public function testConnection(AttendanceSource $source): array
    {
        $connName = 'zktime_test_conn';
        try {
            $connName = $this->connectionFactory->make($source, 'zktime_test_conn');
            
            // 1. Chỉ test bằng câu SQL đơn giản trước
            DB::connection($connName)->select('SELECT 1 AS test');

            // 2. Sau khi kết nối thành công mới kiểm tra các bảng
            $userTable = $source->user_table ?: 'USERINFO';
            $checkInOutTable = $source->checkinout_table ?: 'CHECKINOUT';

            // Tạo các bảng giả lập ở môi trường phát triển local nếu chưa có để test không bị lỗi
            if (app()->environment('local') && (!extension_loaded('sqlsrv') || !extension_loaded('pdo_sqlsrv'))) {
                $schema = Schema::connection($connName);
                if (!$schema->hasTable($userTable)) {
                    $schema->create($userTable, function ($table) use ($source) {
                        $table->integer('USERID')->primary();
                        $table->string($source->employee_code_field ?: 'SSN');
                        $table->string($source->badge_field ?: 'Badgenumber');
                    });
                }
                if (!$schema->hasTable($checkInOutTable)) {
                    $schema->create($checkInOutTable, function ($table) use ($source) {
                        $table->integer('id')->primary();
                        $table->integer('USERID');
                        $table->dateTime($source->check_time_field ?: 'CHECKTIME');
                        $table->string('CHECKTYPE');
                        $table->string('SENSORID');
                    });
                }
            }

            try {
                DB::connection($connName)->table($userTable)->limit(1)->first();
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'message' => "Kết nối thành công nhưng chưa tìm thấy bảng {$userTable}.",
                ];
            }

            try {
                DB::connection($connName)->table($checkInOutTable)->limit(1)->first();
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'message' => "Kết nối thành công nhưng chưa tìm thấy bảng {$checkInOutTable}.",
                ];
            }

            $userCount = DB::connection($connName)->table($userTable)->count();
            $logCount = DB::connection($connName)->table($checkInOutTable)->count();

            $source->update([
                'last_tested_at' => now(),
                'connection_status' => 'success',
                'last_error' => null,
            ]);

            return [
                'ok' => true,
                'message' => 'Kết nối thành công.',
                'user_count' => $userCount,
                'log_count' => $logCount,
            ];

        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            $lowerError = strtolower($errorMessage);
            $friendlyMessage = 'Lỗi kết nối CSDL: ' . $errorMessage;

            if (str_contains($lowerError, 'server chưa cài pdo_sqlsrv') || 
                str_contains($lowerError, 'could not find driver') || 
                str_contains($lowerError, 'driver sqlsrv')) {
                $friendlyMessage = 'Server chưa cài pdo_sqlsrv';
            } elseif (str_contains($lowerError, 'imssp') || str_contains($lowerError, 'unsupported attribute')) {
                $friendlyMessage = 'Cấu hình PDO option không phù hợp với SQL Server, đã bỏ các option không hỗ trợ';
            } elseif (str_contains($lowerError, '28000') || str_contains($lowerError, 'login failed') || str_contains($lowerError, 'access denied')) {
                $friendlyMessage = 'Sai tài khoản hoặc mật khẩu SQL Server';
            } elseif (str_contains($lowerError, '08001') || str_contains($lowerError, 'hyt00') || str_contains($lowerError, 'connection refused') || str_contains($lowerError, 'timeout') || str_contains($lowerError, 'unreachable') || str_contains($lowerError, 'locate server')) {
                $friendlyMessage = 'Không kết nối được SQL Server';
            }

            // Log lỗi rõ ràng không có password
            \Illuminate\Support\Facades\Log::error('ZKTime Connection Test Failed', [
                'source_id' => $source->id,
                'host' => $source->host,
                'port' => $source->port,
                'database' => $source->database_name,
                'username' => $source->username,
                'driver' => 'sqlsrv',
                'error' => $errorMessage,
                'trace' => collect($e->getTrace())->slice(0, 5)->map(fn($t) => ($t['file'] ?? '') . ':' . ($t['line'] ?? ''))->toArray(),
            ]);

            $source->update([
                'last_tested_at' => now(),
                'connection_status' => 'failed',
                'last_error' => $friendlyMessage,
            ]);

            return [
                'ok' => false,
                'message' => $friendlyMessage,
            ];
        }
    }

    /**
     * Synchronizes checkin/out logs from a ZKTime source database.
     *
     * @param AttendanceSource $source
     * @param string $from  Format: Y-m-d
     * @param string $to    Format: Y-m-d
     * @param bool $dryRun
     * @return array{dry_run: bool, source_id: int, source_name: string, company_id: int, total_read: int, new_logs?: int, duplicates?: int, unmapped?: int, inserted?: int, skipped?: int}
     * @throws RuntimeException
     */
    public function sync(AttendanceSource $source, string $from, string $to, bool $dryRun = false): array
    {
        $startedAt = now();
        $totalRead = 0;
        $inserted = 0;
        $skipped = 0;
        $unmapped = 0;

        // Check if closed/locked periods are affected
        $this->assertPeriodNotLocked($source->company_id, $from, $to);

        try {
            $connName = $this->connectionFactory->make($source);
            $userTable = $source->user_table;
            $checkInOutTable = $source->checkinout_table;

            // Fetch records from SQL Server
            $records = DB::connection($connName)
                ->table("{$checkInOutTable} as c")
                ->join("{$userTable} as u", 'c.USERID', '=', 'u.USERID')
                ->select(
                    'c.USERID as device_user_id',
                    "u.{$source->employee_code_field} as employee_code",
                    "u.{$source->badge_field} as badge_number",
                    "c.{$source->check_time_field} as check_time",
                    'c.CHECKTYPE as check_type',
                    'c.SENSORID as device_code'
                )
                ->where("c.{$source->check_time_field}", '>=', $from . ' 00:00:00')
                ->where("c.{$source->check_time_field}", '<=', $to . ' 23:59:59')
                ->get();

            $totalRead = count($records);

            // Fetch mappings and codes for fast resolution
            $mappings = $this->getBiometricMap($source->company_id);
            $employeeCodes = Employee::where('company_id', $source->company_id)
                ->pluck('id', 'employee_code')
                ->all();

            if ($dryRun) {
                $newLogs = 0;
                $duplicates = 0;
                $unmappedLogs = 0;

                foreach ($records as $record) {
                    $deviceUserId = trim((string) $record->device_user_id);
                    $empCode = trim((string) $record->employee_code);
                    $checkTime = Carbon::parse($record->check_time, $source->timezone)->timezone(config('app.timezone'));

                    // Unique Hash key to prevent duplicate entries
                    $hashInput = $source->id . '|' . $deviceUserId . '|' . $checkTime->toDateTimeString();
                    $uniqueHash = hash('sha256', $hashInput);

                    $exists = AttendanceRawLog::where('attendance_source_id', $source->id)
                        ->where('device_user_id', $deviceUserId)
                        ->where('check_time', $checkTime->toDateTimeString())
                        ->exists();

                    if ($exists) {
                        $duplicates++;
                        continue;
                    }

                    $employeeId = $mappings[$deviceUserId] ?? $employeeCodes[$empCode] ?? null;
                    if ($employeeId) {
                        $newLogs++;
                    } else {
                        $unmappedLogs++;
                    }
                }

                return [
                    'dry_run' => true,
                    'source_id' => $source->id,
                    'source_name' => $source->name,
                    'company_id' => $source->company_id,
                    'total_read' => $totalRead,
                    'new_logs' => $newLogs,
                    'duplicates' => $duplicates,
                    'unmapped' => $unmappedLogs,
                ];
            }

            // Real run
            DB::beginTransaction();
            try {
                $affectedEmployees = [];

                foreach ($records as $record) {
                    $deviceUserId = trim((string) $record->device_user_id);
                    $empCode = trim((string) $record->employee_code);
                    $checkTime = Carbon::parse($record->check_time, $source->timezone)->timezone(config('app.timezone'));

                    // Unique Hash key
                    $hashInput = $source->id . '|' . $deviceUserId . '|' . $checkTime->toDateTimeString();
                    $uniqueHash = hash('sha256', $hashInput);

                    // Check if already in staging raw logs
                    $exists = AttendanceRawLog::where('attendance_source_id', $source->id)
                        ->where('device_user_id', $deviceUserId)
                        ->where('check_time', $checkTime->toDateTimeString())
                        ->first();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    // Try to resolve employee
                    $employeeId = $mappings[$deviceUserId] ?? null;

                    if (!$employeeId && isset($employeeCodes[$empCode])) {
                        // Fallback matching by employee code and auto create map
                        $employeeId = $employeeCodes[$empCode];
                        EmployeeAttendanceMapping::create([
                            'company_id' => $source->company_id,
                            'employee_id' => $employeeId,
                            'employee_code' => $empCode,
                            'device_user_id' => $deviceUserId,
                        ]);
                        $mappings[$deviceUserId] = $employeeId;
                    }

                    $status = $employeeId ? 'processed' : 'unmapped';

                    if ($employeeId) {
                        $inserted++;
                    } else {
                        $unmapped++;
                    }

                    // Log in staging table
                    AttendanceRawLog::create([
                        'company_id' => $source->company_id,
                        'attendance_source_id' => $source->id,
                        'employee_id' => $employeeId,
                        'employee_code' => $empCode,
                        'device_user_id' => $deviceUserId,
                        'check_time' => $checkTime->toDateTimeString(),
                        'raw_payload' => (array) $record,
                        'unique_hash' => $uniqueHash,
                        'status' => $status,
                    ]);

                    // Standardize log to punches if mapped
                    if ($employeeId) {
                        $punchType = $this->normalizePunchType($record->check_type);

                        AttendancePunch::firstOrCreate([
                            'company_id' => $source->company_id,
                            'employee_id' => $employeeId,
                            'punched_at' => $checkTime->toDateTimeString(),
                        ], [
                            'punch_type' => $punchType,
                            'source' => 'device',
                            'is_valid' => true,
                            'validation_message' => 'Đồng bộ tự động từ ZKTime SQL: ' . $source->name,
                        ]);

                        $affectedEmployees[$employeeId][] = $checkTime;
                    }
                }

                // Rebuild daily attendance logs for affected employees and dates
                foreach ($affectedEmployees as $employeeId => $punchedTimes) {
                    $workDates = collect($punchedTimes)
                        ->map(fn($t) => $this->resolveWorkDate($t))
                        ->unique()
                        ->all();

                    foreach ($workDates as $workDate) {
                        $this->rebuildDailyLog($employeeId, $source->company_id, $workDate);
                    }
                }

                $source->update([
                    'last_synced_at' => now(),
                    'connection_status' => 'success',
                    'last_error' => null,
                ]);

                // Record sync execution log
                AttendanceSyncLog::create([
                    'attendance_source_id' => $source->id,
                    'company_id' => $source->company_id,
                    'started_at' => $startedAt,
                    'finished_at' => now(),
                    'status' => 'success',
                    'total_read' => $totalRead,
                    'inserted' => $inserted,
                    'skipped' => $skipped,
                    'unmapped' => $unmapped,
                ]);

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            return [
                'dry_run' => false,
                'source_id' => $source->id,
                'source_name' => $source->name,
                'company_id' => $source->company_id,
                'total_read' => $totalRead,
                'inserted' => $inserted,
                'skipped' => $skipped,
                'unmapped' => $unmapped,
            ];

        } catch (\Throwable $e) {
            AttendanceSyncLog::create([
                'attendance_source_id' => $source->id,
                'company_id' => $source->company_id,
                'started_at' => $startedAt,
                'finished_at' => now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $source->update([
                'connection_status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Resolves punch type code to 'in' or 'out'.
     */
    private function normalizePunchType(?string $checkType): string
    {
        $checkType = trim(strtoupper((string) $checkType));
        // Common ZKTime CHECKTYPE codes: O=Check Out, 1=Check Out, 2=Break Out, 5=OT Out
        if (in_array($checkType, ['O', '1', '2', '5', 'OUT'], true)) {
            return 'out';
        }
        return 'in';
    }

    /**
     * Resolves the work date boundary for a check time (shifts starting before 06:00 belong to previous day).
     */
    private function resolveWorkDate(Carbon $punchedAt): string
    {
        $localTime = $punchedAt->copy()->timezone('Asia/Ho_Chi_Minh');
        if ($localTime->hour < 6) {
            return $localTime->copy()->subDay()->toDateString();
        }
        return $localTime->toDateString();
    }

    /**
     * Rebuilds the daily attendance log for a specific employee and work date based on punches.
     */
    private function rebuildDailyLog(int $employeeId, int $companyId, string $workDate): void
    {
        $start = Carbon::parse($workDate, 'Asia/Ho_Chi_Minh')->hour(6)->minute(0)->second(0)->timezone(config('app.timezone'));
        $end = Carbon::parse($workDate, 'Asia/Ho_Chi_Minh')->addDay()->hour(5)->minute(59)->second(59)->timezone(config('app.timezone'));

        // Retrieve all punches on this work date range
        $punches = AttendancePunch::where('employee_id', $employeeId)
            ->whereBetween('punched_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->orderBy('punched_at')
            ->get();

        if ($punches->isEmpty()) {
            AttendanceLog::where('employee_id', $employeeId)->where('work_date', $workDate)->delete();
            return;
        }

        $checkIn = $punches->first()->punched_at;
        $checkOut = $punches->count() > 1 ? $punches->last()->punched_at : null;

        AttendanceLog::updateOrCreate(
            ['employee_id' => $employeeId, 'work_date' => Carbon::parse($workDate)],
            [
                'company_id' => $companyId,
                'check_in_at' => $checkIn,
                'check_out_at' => $checkOut,
                'source' => 'device',
                'location_status' => 'device_trusted',
            ]
        );
    }

    /**
     * Checks whether the synchronization covers periods that are locked for attendance/payroll.
     */
    private function assertPeriodNotLocked(int $companyId, string $from, string $to): void
    {
        $start = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to)->endOfMonth();

        $periods = [];
        $cursor = $start->copy();
        while ($cursor <= $end) {
            $periods[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        $lockService = app(AttendancePeriodLockService::class);
        foreach ($periods as $period) {
            if ($lockService->isLocked($companyId, $period)) {
                throw new RuntimeException("Kỳ công {$period} đã bị khóa, không thể đồng bộ.");
            }
        }
    }

    /**
     * Returns a map of device_user_id => employee_id
     */
    private function getBiometricMap(int $companyId): array
    {
        return EmployeeAttendanceMapping::where('company_id', $companyId)
            ->pluck('employee_id', 'device_user_id')
            ->all();
    }

    /**
     * Synchronizes Employee fingerprint code (Mã vân tay / Badgenumber) from ZKTime database to HRM.
     */
    public function syncFingerprintCodes(AttendanceSource $source, bool $dryRun = false, bool $force = false): array
    {
        $connName = 'zktime_badge_sync';
        $startedAt = now()->toDateTimeString();
        
        try {
            $connName = $this->connectionFactory->make($source, 'zktime_badge_sync');
            $userTable = $source->user_table ?: 'USERINFO';
            
            // 1. Check if user_table exists
            if (!Schema::connection($connName)->hasTable($userTable)) {
                throw new RuntimeException("Không tìm thấy bảng {$userTable} trong cơ sở dữ liệu ZKTime.");
            }

            // 2. Detect columns in ZKTime USERINFO
            $columns = Schema::connection($connName)->getColumnListing($userTable);
            $columnsLower = array_map('strtolower', $columns);

            $badgeCol = null;
            $useridCol = null;
            $nameCol = null;
            $ssnCol = null;

            foreach ($columns as $col) {
                $lower = strtolower($col);
                if ($lower === 'badgenumber') $badgeCol = $col;
                elseif ($lower === 'userid') $useridCol = $col;
                elseif ($lower === 'name') $nameCol = $col;
                elseif ($lower === 'ssn') $ssnCol = $col;
            }

            // Fallback column detection if not standard
            if (!$badgeCol) {
                if (in_array('badgenumber', $columnsLower)) $badgeCol = $columns[array_search('badgenumber', $columnsLower)];
                else if (in_array('badge_number', $columnsLower)) $badgeCol = $columns[array_search('badge_number', $columnsLower)];
                else if (in_array('badge', $columnsLower)) $badgeCol = $columns[array_search('badge', $columnsLower)];
            }
            if (!$useridCol) {
                if (in_array('userid', $columnsLower)) $useridCol = $columns[array_search('userid', $columnsLower)];
                else if (in_array('user_id', $columnsLower)) $useridCol = $columns[array_search('user_id', $columnsLower)];
            }
            if (!$nameCol) {
                if (in_array('name', $columnsLower)) $nameCol = $columns[array_search('name', $columnsLower)];
            }
            if (!$ssnCol) {
                if (in_array('ssn', $columnsLower)) $ssnCol = $columns[array_search('ssn', $columnsLower)];
                else if (in_array('employee_code', $columnsLower)) $ssnCol = $columns[array_search('employee_code', $columnsLower)];
            }

            if (!$badgeCol) {
                throw new RuntimeException("Không tìm thấy cột Badgenumber trong bảng {$userTable}.");
            }

            // Build query dynamically select what exists
            $selectCols = [$badgeCol];
            if ($useridCol) $selectCols[] = $useridCol;
            if ($nameCol) $selectCols[] = $nameCol;
            if ($ssnCol) $selectCols[] = $ssnCol;

            // 3. Fetch ZKTime Users
            $zkUsers = DB::connection($connName)->table($userTable)->select($selectCols)->get();

            // 4. Determine ERP Employee Code column dynamically
            $empTable = (new Employee)->getTable();
            $employeeCodeColumn = 'employee_code';
            foreach (['employee_code', 'code', 'staff_code', 'employee_no'] as $col) {
                if (Schema::hasColumn($empTable, $col)) {
                    $employeeCodeColumn = $col;
                    break;
                }
            }

            // 5. Fetch HRM employees
            $employees = Employee::with('profile')
                ->where('company_id', $source->company_id)
                ->get()
                ->keyBy($employeeCodeColumn);

            $matchedCount = 0;
            $unmatchedCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $warnings = [];
            $toUpdate = [];
            $updatesList = [];

            // 6. Detect duplicates in ZKTime
            $badgeGroups = [];
            foreach ($zkUsers as $user) {
                $badge = trim((string) $user->{$badgeCol});
                if ($badge !== '') {
                    $badgeGroups[$badge][] = $user;
                }
            }

            foreach ($badgeGroups as $badge => $users) {
                if (count($users) > 1) {
                    $warnings[] = "Mã Badgenumber '{$badge}' bị trùng lặp cho " . count($users) . " người dùng trong ZKTime.";
                }
            }

            // 7. Process ZKTime users
            foreach ($zkUsers as $user) {
                $badgenumber = trim((string) $user->{$badgeCol});
                $userid = $useridCol ? trim((string) $user->{$useridCol}) : '—';
                $name = $nameCol ? trim((string) $user->{$nameCol}) : '—';

                // Determine mapping code: prioritize SSN, fallback to Badgenumber
                $mapCode = '';
                if ($ssnCol) {
                    $mapCode = trim((string) ($user->{$ssnCol} ?? ''));
                }
                if ($mapCode === '') {
                    $mapCode = $badgenumber;
                }

                if ($badgenumber === '') {
                    $warnings[] = "ID máy {$userid} (Tên: {$name}) có Badgenumber rỗng.";
                    continue;
                }

                if ($mapCode === '') {
                    $warnings[] = "ID máy {$userid} (Tên: {$name}) có mã liên kết (SSN/Badgenumber) rỗng.";
                    continue;
                }

                // Match with ERP employee
                $employee = $employees->get($mapCode);

                if (!$employee) {
                    $warnings[] = "Không tìm thấy nhân viên HRM tương ứng với mã liên kết: {$mapCode}";
                    $unmatchedCount++;
                    continue;
                }

                $matchedCount++;
                $currentBiometricId = $employee->profile?->biometric_id;

                if (empty($currentBiometricId)) {
                    $toUpdate[] = [
                        'employee' => $employee,
                        'new_value' => $badgenumber,
                        'old_value' => null
                    ];
                    $updatesList[] = [
                        'employee_code' => $employee->employee_code,
                        'full_name' => $employee->full_name,
                        'old_value' => '—',
                        'new_value' => $badgenumber,
                        'status' => 'Sẽ cập nhật'
                    ];
                    $updatedCount++;
                } else {
                    if ($currentBiometricId === $badgenumber) {
                        $skippedCount++;
                    } else {
                        if ($force) {
                            $toUpdate[] = [
                                'employee' => $employee,
                                'new_value' => $badgenumber,
                                'old_value' => $currentBiometricId
                            ];
                            $updatesList[] = [
                                'employee_code' => $employee->employee_code,
                                'full_name' => $employee->full_name,
                                'old_value' => $currentBiometricId,
                                'new_value' => $badgenumber,
                                'status' => 'Ghi đè'
                            ];
                            $updatedCount++;
                        } else {
                            $warnings[] = "Nhân viên {$employee->employee_code} ({$employee->full_name}) đã có Mã vân tay là '{$currentBiometricId}', giá trị mới từ ZKTime là '{$badgenumber}'. Không ghi đè nếu không có --force.";
                            $skippedCount++;
                        }
                    }
                }
            }

            // 8. Execute updates if not dryRun
            $auditRecords = [];
            if (!$dryRun && !empty($toUpdate)) {
                DB::transaction(function () use ($toUpdate, &$auditRecords, $startedAt) {
                    foreach ($toUpdate as $item) {
                        $employee = $item['employee'];
                        $newValue = $item['new_value'];
                        $oldValue = $item['old_value'];

                        $profile = $employee->profile;
                        if (!$profile) {
                            $profile = new EmployeeProfile();
                            $profile->employee_id = $employee->id;
                        }
                        $profile->biometric_id = $newValue;
                        $profile->save();

                        $auditRecords[] = [
                            'employee_id' => $employee->id,
                            'employee_code' => $employee->employee_code,
                            'old_fingerprint_code' => $oldValue,
                            'new_badgenumber' => $newValue,
                            'synced_at' => $startedAt
                        ];
                    }
                });

                // Write to audit log file (append JSON lines)
                $logFile = storage_path('logs/zktime_sync_badge_audit.json');
                if (!file_exists(dirname($logFile))) {
                    mkdir(dirname($logFile), 0755, true);
                }
                foreach ($auditRecords as $rec) {
                    file_put_contents($logFile, json_encode($rec, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
                }

                // Log to standard Laravel Log
                \Illuminate\Support\Facades\Log::info("ZKTime Badge Sync: Updated " . count($toUpdate) . " employee fingerprint codes (source ID: {$source->id}).");
            }

            return [
                'ok' => true,
                'dry_run' => $dryRun,
                'force' => $force,
                'total_read' => count($zkUsers),
                'matched_count' => $matchedCount,
                'unmatched_count' => $unmatchedCount,
                'updated_count' => $updatedCount,
                'skipped_count' => $skippedCount,
                'warnings' => $warnings,
                'updates' => $updatesList
            ];

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("ZKTime Badge Sync Failed: " . $e->getMessage(), [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
