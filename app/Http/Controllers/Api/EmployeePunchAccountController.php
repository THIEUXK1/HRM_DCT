<?php



namespace App\Http\Controllers\Api;



use App\Http\Requests\ProvisionPunchAccountRequest;

use App\Models\Employee;

use App\Services\Attendance\AttendancePunchAccountService;

use Illuminate\Http\JsonResponse;

use RuntimeException;



class EmployeePunchAccountController extends ApiController

{

    public function show(Employee $employee, AttendancePunchAccountService $service): JsonResponse

    {

        return $this->success($service->status($employee));

    }



    public function provision(

        ProvisionPunchAccountRequest $request,

        Employee $employee,

        AttendancePunchAccountService $service,

    ): JsonResponse {

        try {

            $result = $service->provision(

                $employee,

                $request->boolean('punch_gps'),

                $request->boolean('punch_qr'),

            );

        } catch (RuntimeException $e) {

            return $this->error($e->getMessage(), 422);

        }



        return $this->success([

            ...$result,

            'message' => 'Đã cấp tài khoản chấm công. NV đăng nhập bằng mã NV và mật khẩu mặc định, bắt buộc đổi mật khẩu lần đầu.',

        ], 201);

    }



    public function revoke(Employee $employee, AttendancePunchAccountService $service): JsonResponse

    {

        $service->revoke($employee);



        return $this->success(['message' => 'Đã thu hồi quyền chấm công GPS/QR.']);

    }

}


