<?php

namespace App\Services\Attendance;

use App\Models\AttendanceGeofenceZone;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GeofenceService
{
    /** Khoảng cách Haversine (mét). */
    public function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($a)));
    }

    public function isInsideZone(AttendanceGeofenceZone $zone, float $latitude, float $longitude): bool
    {
        $distance = $this->distanceMeters(
            $latitude,
            $longitude,
            (float) $zone->latitude,
            (float) $zone->longitude,
        );

        return $distance <= (int) $zone->radius_meters;
    }

    /** Vùng NV được phép chấm: vùng chung công ty (branch_id null) + vùng chi nhánh của NV. */
    public function eligibleZonesQuery(int $companyId, ?int $branchId): Builder
    {
        return AttendanceGeofenceZone::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function (Builder $q) use ($branchId) {
                $q->whereNull('branch_id');
                if ($branchId) {
                    $q->orWhere('branch_id', $branchId);
                }
            });
    }

    /**
     * @return Collection<int, AttendanceGeofenceZone>
     */
    public function zonesForEmployee(Employee $employee, ?string $source = null): Collection
    {
        $zones = $this->eligibleZonesQuery($employee->company_id, $employee->branch_id)->get();

        if ($source) {
            $zones = $zones->filter(fn (AttendanceGeofenceZone $zone) => $zone->allowsSource($source));
        }

        return $zones->values();
    }

    /**
     * @return Collection<int, AttendanceGeofenceZone>
     */
    public function matchingZones(
        int $companyId,
        float $latitude,
        float $longitude,
        ?string $source = null,
    ): Collection {
        $zones = AttendanceGeofenceZone::where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        return $zones->filter(function (AttendanceGeofenceZone $zone) use ($latitude, $longitude, $source) {
            if ($source && ! $zone->allowsSource($source)) {
                return false;
            }

            return $this->isInsideZone($zone, $latitude, $longitude);
        })->values();
    }

    /**
     * @return Collection<int, AttendanceGeofenceZone>
     */
    public function matchingZonesForEmployee(
        Employee $employee,
        float $latitude,
        float $longitude,
        ?string $source = null,
    ): Collection {
        return $this->zonesForEmployee($employee, $source)
            ->filter(fn (AttendanceGeofenceZone $zone) => $this->isInsideZone($zone, $latitude, $longitude))
            ->values();
    }

    public function bestMatch(
        int $companyId,
        float $latitude,
        float $longitude,
        ?string $source = null,
    ): ?AttendanceGeofenceZone {
        $matches = $this->matchingZones($companyId, $latitude, $longitude, $source);
        if ($matches->isEmpty()) {
            return null;
        }

        return $matches->sortBy(fn (AttendanceGeofenceZone $z) => $this->distanceMeters(
            $latitude,
            $longitude,
            (float) $z->latitude,
            (float) $z->longitude,
        ))->first();
    }

    public function bestMatchForEmployee(
        Employee $employee,
        float $latitude,
        float $longitude,
        ?string $source = null,
    ): ?AttendanceGeofenceZone {
        $matches = $this->matchingZonesForEmployee($employee, $latitude, $longitude, $source);
        if ($matches->isEmpty()) {
            return null;
        }

        return $matches->sortBy(fn (AttendanceGeofenceZone $z) => $this->distanceMeters(
            $latitude,
            $longitude,
            (float) $z->latitude,
            (float) $z->longitude,
        ))->first();
    }

    public function zoneAllowedForEmployee(AttendanceGeofenceZone $zone, Employee $employee): bool
    {
        if ($zone->company_id !== $employee->company_id) {
            return false;
        }

        if (! $zone->branch_id) {
            return true;
        }

        return $employee->branch_id && (int) $zone->branch_id === (int) $employee->branch_id;
    }
}
