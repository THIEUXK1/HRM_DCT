<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\AttendanceGeofenceZoneRequest;
use App\Models\AttendanceGeofenceZone;
use Illuminate\Http\JsonResponse;

class AttendanceGeofenceZoneController extends ApiController
{
    public function index(): JsonResponse
    {
        $zones = AttendanceGeofenceZone::query()
            ->with('branch:id,name,code')
            ->orderBy('name')
            ->get();

        return $this->success($zones);
    }

    public function store(AttendanceGeofenceZoneRequest $request): JsonResponse
    {
        $zone = AttendanceGeofenceZone::create($request->validated());

        return $this->success($zone, 201);
    }

    public function update(AttendanceGeofenceZoneRequest $request, AttendanceGeofenceZone $attendanceGeofenceZone): JsonResponse
    {
        $attendanceGeofenceZone->update($request->validated());

        return $this->success($attendanceGeofenceZone->fresh());
    }

    public function destroy(AttendanceGeofenceZone $attendanceGeofenceZone): JsonResponse
    {
        $attendanceGeofenceZone->delete();

        return $this->noContent();
    }

    public function issueGateToken(AttendanceGeofenceZone $attendanceGeofenceZone): JsonResponse
    {
        $issued = $attendanceGeofenceZone->issueGateToken();

        return $this->success([
            ...$issued,
            'zone' => $attendanceGeofenceZone->fresh()->only(['id', 'code', 'name']),
            'message' => 'In QR và dán tại cổng. Mã token chỉ hiển thị một lần.',
        ]);
    }
}
