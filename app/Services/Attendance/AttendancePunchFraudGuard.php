<?php



namespace App\Services\Attendance;



use App\Models\AttendanceLog;

use App\Models\AttendancePunch;

use App\Models\Employee;

use App\Models\User;

use Carbon\Carbon;

use RuntimeException;



class AttendancePunchFraudGuard

{

    public function __construct(

        private readonly GeofenceService $geofence,

    ) {}



    public function assertPunchPermission(User $user, string $source): void

    {

        if ($user->must_change_password) {

            throw new RuntimeException('Bạn phải đổi mật khẩu trước khi chấm công.');

        }



        if ($user->hasRole('admin')) {

            return;

        }



        if (in_array($source, ['mobile', 'field', 'kiosk'], true) && ! $user->can(AttendancePunchAccountService::PERM_GPS)) {

            throw new RuntimeException('Tài khoản chưa được cấp quyền chấm công GPS. Liên hệ HR.');

        }



        if ($source === 'qr' && ! $user->can(AttendancePunchAccountService::PERM_QR)) {

            throw new RuntimeException('Tài khoản chưa được cấp quyền chấm công QR. Liên hệ HR.');

        }

    }



    public function assertBeforePunch(

        Employee $employee,

        string $punchType,

        string $source,

        ?float $latitude,

        ?float $longitude,

        ?int $accuracyMeters,

    ): void {

        $this->assertDuplicatePunchType($employee, $punchType);

        $this->assertCooldown($employee);

        $this->assertGpsQuality($source, $latitude, $longitude, $accuracyMeters);

        $this->assertImpossibleTravel($employee, $latitude, $longitude);

    }



    private function assertCooldown(Employee $employee): void

    {

        $seconds = (int) config('attendance_vn.punch_cooldown_seconds', 60);

        if ($seconds <= 0) {

            return;

        }



        $last = AttendancePunch::where('employee_id', $employee->id)

            ->orderByDesc('punched_at')

            ->first();



        if ($last && $last->punched_at->gt(now()->subSeconds($seconds))) {

            throw new RuntimeException('Vui lòng đợi '.$seconds.' giây trước khi chấm công lại.');

        }

    }



    private function assertDuplicatePunchType(Employee $employee, string $punchType): void

    {

        $today = now()->toDateString();

        $log = AttendanceLog::where('employee_id', $employee->id)

            ->whereDate('work_date', $today)

            ->first();



        if ($punchType === 'in' && $log?->check_in_at) {

            throw new RuntimeException('Bạn đã chấm vào hôm nay. Nếu nhầm, liên hệ HR điều chỉnh bảng công.');

        }



        if ($punchType === 'out') {

            if (! $log?->check_in_at) {

                throw new RuntimeException('Chưa chấm vào — không thể chấm ra.');

            }

            if ($log->check_out_at) {

                throw new RuntimeException('Bạn đã chấm ra hôm nay.');

            }

        }

    }



    private function assertGpsQuality(

        string $source,

        ?float $latitude,

        ?float $longitude,

        ?int $accuracyMeters,

    ): void {

        if (! in_array($source, ['mobile', 'field'], true)) {

            return;

        }



        if ($latitude === null || $longitude === null) {

            return;

        }



        $maxAccuracy = (int) config('attendance_vn.punch_max_gps_accuracy_meters', 100);

        if ($maxAccuracy > 0 && $accuracyMeters !== null && $accuracyMeters > $maxAccuracy) {

            throw new RuntimeException(

                'GPS không đủ chính xác (±'.$accuracyMeters.'m). Di chuyển ra ngoài trời hoặc dùng QR cổng.'

            );

        }

    }



    private function assertImpossibleTravel(Employee $employee, ?float $latitude, ?float $longitude): void

    {

        if ($latitude === null || $longitude === null) {

            return;

        }



        $windowMinutes = (int) config('attendance_vn.punch_impossible_travel_minutes', 10);

        $maxKm = (float) config('attendance_vn.punch_impossible_travel_km', 5);

        if ($windowMinutes <= 0 || $maxKm <= 0) {

            return;

        }



        $last = AttendancePunch::where('employee_id', $employee->id)

            ->whereNotNull('latitude')

            ->whereNotNull('longitude')

            ->where('punched_at', '>=', now()->subMinutes($windowMinutes))

            ->orderByDesc('punched_at')

            ->first();



        if (! $last) {

            return;

        }



        $distanceM = $this->geofence->distanceMeters(

            $latitude,

            $longitude,

            (float) $last->latitude,

            (float) $last->longitude,

        );



        if ($distanceM > ($maxKm * 1000)) {

            throw new RuntimeException(

                'Phát hiện vị trí bất thường (di chuyển quá nhanh). Liên hệ HR nếu bạn chấm công hợp lệ.'

            );

        }

    }

}


