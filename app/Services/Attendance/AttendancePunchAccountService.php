<?php



namespace App\Services\Attendance;



use App\Http\Controllers\Api\AuthController;

use App\Models\AttendancePunch;

use App\Models\AttendanceLog;

use App\Models\Company;

use App\Models\Employee;

use App\Models\User;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Hash;

use RuntimeException;

use Spatie\Permission\Models\Permission;

use Spatie\Permission\Models\Role;



class AttendancePunchAccountService

{

    public const PERM_GPS = 'attendance.punch_gps';

    public const PERM_QR = 'attendance.punch_qr';



    /** @return array{user: User, login: string, default_password: string, permissions: array<int, string>} */

    public function provision(Employee $employee, bool $gps, bool $qr): array

    {

        if (! $gps && ! $qr) {

            throw new RuntimeException('Chọn ít nhất một quyền: GPS hoặc QR.');

        }



        if (! $employee->employee_code) {

            throw new RuntimeException('Nhân viên chưa có mã NV.');

        }



        $defaultPassword = config('attendance_vn.punch_default_password', 'abc@123');

        $tenantId = Company::find($employee->company_id)?->tenant_id;



        return DB::transaction(function () use ($employee, $gps, $qr, $defaultPassword, $tenantId) {

            $user = User::where('employee_id', $employee->id)->first();



            if (! $user) {

                $email = $employee->email ?: strtolower($employee->employee_code).'@punch.local';



                if (User::where('email', $email)->exists()) {

                    $email = strtolower($employee->employee_code).'.'.$employee->id.'@punch.local';

                }



                $user = User::create([

                    'tenant_id' => $tenantId,

                    'employee_id' => $employee->id,

                    'default_company_id' => $employee->company_id,

                    'name' => $employee->full_name,

                    'email' => $email,

                    'password' => $defaultPassword,

                    'must_change_password' => true,

                    'punch_account_provisioned_at' => now(),

                ]);

            } else {

                $user->update([

                    'must_change_password' => true,

                    'password' => $defaultPassword,

                    'punch_account_provisioned_at' => now(),

                ]);

            }



            Role::firstOrCreate(['name' => 'employee']);

            if (! $user->hasRole('employee')) {

                $user->assignRole('employee');

            }



            $this->syncPunchPermissions($user, $gps, $qr);



            if (! $user->companies()->where('companies.id', $employee->company_id)->exists()) {

                $user->companies()->syncWithoutDetaching([$employee->company_id]);

            }



            AuthController::bustUserCache($user->id);



            $permissions = array_values(array_filter([

                $gps ? self::PERM_GPS : null,

                $qr ? self::PERM_QR : null,

            ]));



            return [

                'user' => $user->fresh()->load('roles'),

                'login' => $employee->employee_code,

                'default_password' => $defaultPassword,

                'permissions' => $permissions,

            ];

        });

    }



    public function revoke(Employee $employee): void

    {

        $user = User::where('employee_id', $employee->id)->first();

        if (! $user) {

            return;

        }



        $user->revokePermissionTo([self::PERM_GPS, self::PERM_QR]);

        AuthController::bustUserCache($user->id);

    }



    /** @return array{has_account: bool, login: ?string, user: ?array, punch_permissions: array<int, string>, must_change_password: bool} */

    public function status(Employee $employee): array

    {

        $user = User::where('employee_id', $employee->id)->first();



        if (! $user) {

            return [

                'has_account' => false,

                'login' => $employee->employee_code,

                'user' => null,

                'punch_permissions' => [],

                'must_change_password' => false,

            ];

        }



        $user->load('permissions');



        return [

            'has_account' => true,

            'login' => $employee->employee_code,

            'user' => [

                'id' => $user->id,

                'email' => $user->email,

                'punch_account_provisioned_at' => $user->punch_account_provisioned_at?->toIso8601String(),

            ],

            'punch_permissions' => $user->getDirectPermissions()

                ->pluck('name')

                ->filter(fn ($p) => in_array($p, [self::PERM_GPS, self::PERM_QR], true))

                ->values()

                ->all(),

            'must_change_password' => (bool) $user->must_change_password,

        ];

    }



    private function syncPunchPermissions(User $user, bool $gps, bool $qr): void
    {
        Permission::firstOrCreate(['name' => self::PERM_GPS]);
        Permission::firstOrCreate(['name' => self::PERM_QR]);

        $user->revokePermissionTo([self::PERM_GPS, self::PERM_QR]);

        if ($gps) {
            $user->givePermissionTo(self::PERM_GPS);
        }
        if ($qr) {
            $user->givePermissionTo(self::PERM_QR);
        }
    }
}
