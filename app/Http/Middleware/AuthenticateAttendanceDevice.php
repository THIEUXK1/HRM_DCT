<?php

namespace App\Http\Middleware;

use App\Models\AttendanceDevice;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAttendanceDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Device-Token') ?? $request->input('device_token');

        if (! $token) {
            return response()->json(['message' => 'Thiếu mã xác thực thiết bị (X-Device-Token).'], 401);
        }

        $device = AttendanceDevice::query()
            ->where('api_token_hash', hash('sha256', $token))
            ->where('is_active', true)
            ->first();

        if (! $device) {
            return response()->json(['message' => 'Thiết bị chấm công không hợp lệ hoặc đã ngừng hoạt động.'], 401);
        }

        if (! in_array($device->device_type, ['terminal', 'kiosk'], true)) {
            return response()->json(['message' => 'Thiết bị loại import không hỗ trợ chấm công trực tiếp.'], 403);
        }

        $request->attributes->set('attendance_device', $device);
        CompanyContext::setFromCompany((int) $device->company_id);

        return $next($request);
    }
}
