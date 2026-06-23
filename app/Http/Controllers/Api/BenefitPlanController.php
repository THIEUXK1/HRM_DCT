<?php

namespace App\Http\Controllers\Api;

use App\Models\BenefitEnrollment;
use App\Models\BenefitPlan;
use App\Models\Employee;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BenefitPlanController extends ApiController
{
    // ── Benefit Plans ──────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $companyId = CompanyContext::id();

        $plans = BenefitPlan::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($request->input('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->withCount('activeEnrollments')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return $this->success([
            'plans'      => $plans,
            'categories' => BenefitPlan::CATEGORIES,
            'value_types' => BenefitPlan::VALUE_TYPES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id'          => ['required', 'exists:companies,id'],
            'code'                => ['required', 'string', 'max:50', 'unique:benefit_plans,code'],
            'name'                => ['required', 'string', 'max:255'],
            'category'            => ['required', 'in:' . implode(',', array_keys(BenefitPlan::CATEGORIES))],
            'description'         => ['nullable', 'string'],
            'value_type'          => ['required', 'in:fixed,percentage,reimbursement'],
            'value'               => ['required_unless:value_type,reimbursement', 'nullable', 'numeric', 'min:0'],
            'currency'            => ['nullable', 'string', 'max:10'],
            'eligible_after_days' => ['nullable', 'integer', 'min:0'],
            'is_taxable'          => ['boolean'],
            'effective_date'      => ['nullable', 'date'],
            'expiry_date'         => ['nullable', 'date', 'after_or_equal:effective_date'],
            'is_active'           => ['boolean'],
        ]);

        $plan = BenefitPlan::create($data);

        return $this->success($plan, 201);
    }

    public function show(BenefitPlan $benefitPlan): JsonResponse
    {
        return $this->success(
            $benefitPlan->load(['company', 'activeEnrollments.employee'])
                        ->loadCount('activeEnrollments')
        );
    }

    public function update(Request $request, BenefitPlan $benefitPlan): JsonResponse
    {
        $data = $request->validate([
            'name'                => ['sometimes', 'string', 'max:255'],
            'category'            => ['sometimes', 'in:' . implode(',', array_keys(BenefitPlan::CATEGORIES))],
            'description'         => ['nullable', 'string'],
            'value_type'          => ['sometimes', 'in:fixed,percentage,reimbursement'],
            'value'               => ['nullable', 'numeric', 'min:0'],
            'currency'            => ['nullable', 'string', 'max:10'],
            'eligible_after_days' => ['nullable', 'integer', 'min:0'],
            'is_taxable'          => ['boolean'],
            'effective_date'      => ['nullable', 'date'],
            'expiry_date'         => ['nullable', 'date'],
            'is_active'           => ['boolean'],
        ]);

        $benefitPlan->update($data);

        return $this->success($benefitPlan);
    }

    public function destroy(BenefitPlan $benefitPlan): JsonResponse
    {
        if ($benefitPlan->enrollments()->exists()) {
            return $this->error('Không thể xóa gói đang có nhân viên đăng ký. Vui lòng hủy đăng ký trước.', 422);
        }
        $benefitPlan->delete();

        return $this->noContent();
    }

    // ── Enrollments ────────────────────────────────────────────────────────

    /** List enrollments — filtered by plan or employee */
    public function enrollments(Request $request): JsonResponse
    {
        $companyId = CompanyContext::id();

        $query = BenefitEnrollment::query()
            ->with(['employee:id,first_name,last_name,employee_code,company_id', 'plan:id,name,category,value_type,value,currency'])
            ->when($request->input('plan_id'), fn ($q, $id) => $q->where('benefit_plan_id', $id))
            ->when($request->input('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($companyId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)));

        return $this->success($query->orderByDesc('enrolled_at')->paginate($request->integer('per_page', 50)));
    }

    /** Enroll one or more employees into a plan */
    public function enroll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'benefit_plan_id'  => ['required', 'exists:benefit_plans,id'],
            'employee_ids'     => ['required', 'array', 'min:1'],
            'employee_ids.*'   => ['integer', 'exists:employees,id'],
            'enrolled_at'      => ['nullable', 'date'],
            'expires_at'       => ['nullable', 'date'],
            'override_value'   => ['nullable', 'numeric', 'min:0'],
            'notes'            => ['nullable', 'string'],
        ]);

        $enrolledAt = $data['enrolled_at'] ?? now()->toDateString();
        $created    = 0;
        $skipped    = 0;

        foreach ($data['employee_ids'] as $employeeId) {
            $existing = BenefitEnrollment::where('employee_id', $employeeId)
                ->where('benefit_plan_id', $data['benefit_plan_id'])
                ->first();

            if ($existing) {
                // Re-activate if suspended/cancelled
                if (in_array($existing->status, ['suspended', 'cancelled', 'expired'])) {
                    $existing->update(['status' => 'active', 'enrolled_at' => $enrolledAt]);
                    $created++;
                } else {
                    $skipped++;
                }
                continue;
            }

            BenefitEnrollment::create([
                'employee_id'     => $employeeId,
                'benefit_plan_id' => $data['benefit_plan_id'],
                'status'          => 'active',
                'enrolled_at'     => $enrolledAt,
                'expires_at'      => $data['expires_at'] ?? null,
                'override_value'  => $data['override_value'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'enrolled_by'     => Auth::id(),
            ]);
            $created++;
        }

        return $this->success([
            'enrolled' => $created,
            'skipped'  => $skipped,
            'message'  => "Đã đăng ký {$created} nhân viên" . ($skipped ? ", bỏ qua {$skipped} (đã tồn tại)" : ''),
        ]);
    }

    /** Update a single enrollment (status, override value…) */
    public function updateEnrollment(Request $request, BenefitEnrollment $enrollment): JsonResponse
    {
        $data = $request->validate([
            'status'         => ['sometimes', 'in:active,suspended,expired,cancelled'],
            'expires_at'     => ['nullable', 'date'],
            'override_value' => ['nullable', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string'],
        ]);

        $enrollment->update($data);

        return $this->success($enrollment->load('plan', 'employee'));
    }

    /** Summary statistics for the benefits dashboard */
    public function summary(Request $request): JsonResponse
    {
        $companyId = CompanyContext::id();

        $plans = BenefitPlan::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('is_active', true)
            ->withCount('activeEnrollments')
            ->get();

        $totalEmployees = Employee::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('employment_status', '!=', 'terminated')
            ->count();

        // Cost estimation (fixed plans only)
        $monthlyCost = BenefitEnrollment::query()
            ->active()
            ->whereHas('plan', fn ($q) => $q->where('value_type', 'fixed')
                ->when($companyId, fn ($q2) => $q2->where('company_id', $companyId)))
            ->with('plan:id,value,value_type,is_taxable')
            ->get()
            ->sum(fn ($e) => (float) ($e->override_value ?? $e->plan?->value ?? 0));

        $byCategory = $plans->groupBy('category')->map(fn ($group, $cat) => [
            'category'       => $cat,
            'label'          => BenefitPlan::CATEGORIES[$cat] ?? $cat,
            'plan_count'     => $group->count(),
            'enrolled_count' => $group->sum('active_enrollments_count'),
        ])->values();

        return $this->success([
            'total_plans'      => $plans->count(),
            'total_enrolled'   => $plans->sum('active_enrollments_count'),
            'total_employees'  => $totalEmployees,
            'monthly_cost_est' => $monthlyCost,
            'by_category'      => $byCategory,
            'plans'            => $plans,
        ]);
    }

    /** GET employee's benefit package */
    public function employeeBenefits(int $employeeId): JsonResponse
    {
        $enrollments = BenefitEnrollment::forEmployee($employeeId)
            ->with('plan')
            ->orderBy('status')
            ->get();

        return $this->success($enrollments);
    }
}
