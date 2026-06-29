<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EmployeeSyncController extends ApiController
{
    const APIS = [
        'PFVN' => 'https://bptehr.bestpacific.com/ehr/open/rmt/getPfna',
        'MEGA' => 'https://bptehr.bestpacific.com/ehr/open/rmt/getMega',
        'BPVN' => 'https://bptehr.bestpacific.com/ehr/open/rmt/getBpvn',
    ];

    const CHUNK_SIZE = 50;

    /**
     * Bước 1: Gọi API ngoài, cache danh sách, trả về tổng số để frontend hiển thị progress.
     */
    public function prepare(Request $request): JsonResponse
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'api_type'   => 'required|in:PFVN,MEGA,BPVN',
        ]);

        $apiType  = strtoupper($request->api_type);
        $ym       = now()->format('Y-m');
        $response = Http::timeout(30)->get(self::APIS[$apiType], ['ym' => $ym]);

        if ($response->failed() || $response->json('code') !== 0) {
            return $this->error('Không thể kết nối API ' . $apiType, 502);
        }

        $employees = $response->json('rows.employees', []);
        $cacheKey  = 'emp_sync_' . $apiType . '_' . auth()->id();

        Cache::put($cacheKey, $employees, now()->addMinutes(15));

        return $this->success([
            'cache_key' => $cacheKey,
            'total'     => count($employees),
            'ym'        => $ym,
            'api_type'  => $apiType,
        ]);
    }

    /**
     * Bước 2: Xử lý 1 chunk (offset → offset+50).
     * Frontend gọi lặp lại đến khi processed >= total.
     */
    public function execute(Request $request): JsonResponse
    {
        $request->validate([
            'cache_key'  => 'required|string',
            'company_id' => 'required|exists:companies,id',
            'offset'     => 'required|integer|min:0',
        ]);

        $cacheKey  = $request->cache_key;
        $companyId = $request->integer('company_id');
        $offset    = $request->integer('offset');

        $all = Cache::get($cacheKey);
        if ($all === null) {
            return $this->error('Phiên đồng bộ hết hạn, vui lòng bắt đầu lại.', 410);
        }

        $chunk   = array_slice($all, $offset, self::CHUNK_SIZE);
        $total   = count($all);
        $created = 0;
        $updated = 0;
        $errors  = [];

        $branch = $this->getOrCreateDefaultBranch($companyId);

        foreach ($chunk as $row) {
            try {
                $department = $this->getOrCreateDepartment($companyId, $branch->id, $row['DEPT_CODE'], $row['DEPT_NAME']);
                $position   = $this->getOrCreatePosition($department->id, $row['POSITION_CODE'], $row['POSITION_NAME']);
                $vnName     = $this->extractVietnameseName($row['USER_NAME']);

                // STATUS: 0=đã nghỉ việc, 1=đang làm, 2=đang thử việc
                $status = (int) ($row['STATUS'] ?? 1);

                $data = [
                    'company_id'        => $companyId,
                    'branch_id'         => $branch->id,
                    'department_id'     => $department->id,
                    'position_id'       => $position->id,
                    'full_name'         => $vnName,
                    'full_name_raw'     => trim($row['USER_NAME'] . ' ' . ($row['ENAME'] ?? '')),
                    'chinese_name'      => $row['ENAME'] ?? null,
                    'first_name'        => $vnName,
                    'last_name'         => '',
                    'is_active'         => $status !== 0,
                    'employment_status' => match ($status) {
                        0       => 'terminated',  // đã nghỉ việc
                        2       => 'probation',   // đang thử việc
                        default => 'active',      // STATUS=1 đang làm
                    },
                    'hire_date'         => isset($row['ENTRY_DATE']) ? substr($row['ENTRY_DATE'], 0, 10) : null,
                    'termination_date'  => $status === 0 && isset($row['PRO_LEAVE_DATE'])
                        ? substr($row['PRO_LEAVE_DATE'], 0, 10)
                        : null,
                    'bank_name'         => $row['DEPOSIT_BANK'] ?? null,
                    'source_company'    => strtoupper($request->input('api_type', '')),
                ];

                // EMPNO là duy nhất toàn hệ thống — nếu tồn tại thì chỉ update, kể cả đổi công ty
                $exists = Employee::where('employee_code', $row['EMPNO'])->first();

                if ($exists) {
                    $exists->update($data);
                    $updated++;
                } else {
                    Employee::create(array_merge($data, ['employee_code' => $row['EMPNO']]));
                    $created++;
                }
            } catch (\Throwable $e) {
                $errors[] = ($row['EMPNO'] ?? '?') . ': ' . $e->getMessage();
            }
        }

        $processed = $offset + count($chunk);

        // Xóa cache khi xong
        if ($processed >= $total) {
            Cache::forget($cacheKey);
        }

        return $this->success([
            'processed' => $processed,
            'total'     => $total,
            'created'   => $created,
            'updated'   => $updated,
            'errors'    => $errors,
            'done'      => $processed >= $total,
        ]);
    }

    private function getOrCreateDefaultBranch(int $companyId): Branch
    {
        return Branch::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'HQ'],
            ['name' => 'Trụ sở chính', 'is_active' => true]
        );
    }

    private function getOrCreateDepartment(int $companyId, int $branchId, string $code, string $rawName): Department
    {
        $name = $this->cleanBilingualName($rawName);

        $dept = Department::whereHas('branch', fn ($q) => $q->where('company_id', $companyId))
            ->where('code', $code)->first();

        if ($dept) {
            $dept->update(['name' => $name]);
            return $dept;
        }

        return Department::create([
            'branch_id' => $branchId,
            'code'      => $code,
            'name'      => $name,
            'is_active' => true,
        ]);
    }

    private function getOrCreatePosition(int $departmentId, string $code, string $rawName): Position
    {
        $name = $this->cleanBilingualName($rawName);
        return Position::firstOrCreate(
            ['department_id' => $departmentId, 'code' => $code],
            ['name' => $name, 'is_active' => true]
        );
    }

    private function cleanBilingualName(string $raw): string
    {
        return trim(preg_replace('/[\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}]+/u', '', $raw));
    }

    private function extractVietnameseName(string $raw): string
    {
        return trim(preg_replace('/[\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}]+/u', '', $raw));
    }
}
