<?php

namespace App\Services\Recruitment;

use App\Models\Candidate;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\EmploymentContract;
use App\Models\Offer;
use App\Models\Course;
use App\Models\EmployeeOnboardingTask;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use App\Models\TrainingClass;
use App\Models\TrainingEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class HireCandidateService
{
    public function hire(Candidate $candidate, array $employeeData = []): Employee
    {
        if ($candidate->employee_id) {
            throw new RuntimeException('Candidate already hired.');
        }

        $offer = Offer::where('candidate_id', $candidate->id)
            ->where('status', 'accepted')
            ->latest()
            ->first();

        if (! $offer) {
            throw new RuntimeException('Ứng viên cần có offer đã được chấp nhận (accepted) trước khi chuyển thành nhân viên.');
        }

        return DB::transaction(function () use ($candidate, $employeeData, $offer) {
            $nameParts = explode(' ', $candidate->full_name, 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? $nameParts[0];

            $employee = Employee::create(array_merge([
                'company_id' => $candidate->company_id,
                'branch_id' => $employeeData['branch_id'] ?? null,
                'department_id' => $employeeData['department_id'] ?? null,
                'position_id' => $employeeData['position_id'] ?? null,
                'employee_code' => $employeeData['employee_code'] ?? $this->generateEmployeeCode($candidate->company_id),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'full_name' => $candidate->full_name,
                'email' => $candidate->email ?? Str::slug($candidate->full_name).'@company.local',
                'phone' => $candidate->phone,
                'hire_date' => $offer?->start_date ?? now()->toDateString(),
                'employment_status' => 'probation',
                'is_active' => true,
            ], $employeeData));

            if ($offer) {
                EmploymentContract::create([
                    'employee_id' => $employee->id,
                    'contract_number' => 'CTR-'.$employee->id.'-'.now()->format('Ymd'),
                    'contract_type' => $offer->contract_type,
                    'start_date' => $offer->start_date,
                    'salary_base' => $offer->salary_base,
                    'salary_currency' => 'VND',
                    'status' => 'active',
                ]);

            }

            $candidate->update([
                'employee_id' => $employee->id,
                'stage' => 'hired',
            ]);

            $this->syncProfileFromCandidate($employee, $candidate);
            $this->spawnOnboardingTasks($employee, $employeeData['onboarding_buddy_user_id'] ?? null);
            $this->enrollOnboardingCourse($employee);
            $this->linkOrCreateUserAccount($employee);

            return $employee->load(['contracts', 'profile', 'onboardingTasks.task']);
        });
    }

    protected function generateEmployeeCode(int $companyId): string
    {
        $count = Employee::where('company_id', $companyId)->count() + 1;

        return 'EMP-'.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }

    protected function syncProfileFromCandidate(Employee $employee, Candidate $candidate): void
    {
        $skills = $candidate->skills;
        if (is_array($skills)) {
            $skills = implode(', ', $skills);
        }

        EmployeeProfile::updateOrCreate(
            ['employee_id' => $employee->id],
            array_filter([
                'skills' => $skills,
                'certificate_summary' => $candidate->experience_summary,
            ])
        );
    }

    protected function spawnOnboardingTasks(Employee $employee, ?int $buddyUserId = null): void
    {
        $tenantId = Company::find($employee->company_id)?->tenant_id;
        $template = OnboardingTemplate::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return;
        }

        OnboardingTask::where('onboarding_template_id', $template->id)
            ->orderBy('sort_order')
            ->each(function (OnboardingTask $task) use ($employee, $buddyUserId) {
                $assignedTo = null;
                if (str_contains(mb_strtolower($task->title), 'buddy') || str_contains(mb_strtolower($task->title), 'hướng dẫn')) {
                    $assignedTo = $buddyUserId;
                }

                EmployeeOnboardingTask::create([
                    'employee_id' => $employee->id,
                    'onboarding_task_id' => $task->id,
                    'status' => 'pending',
                    'assigned_to' => $assignedTo,
                ]);
            });
    }

    /**
     * Ensure a User account exists for the new employee and has the correct
     * company context (default_company_id + employee_id linkage).
     */
    protected function linkOrCreateUserAccount(Employee $employee): void
    {
        $existing = User::where('email', $employee->email)->first();

        if ($existing) {
            $existing->update([
                'employee_id' => $employee->id,
                'default_company_id' => $existing->default_company_id ?? $employee->company_id,
                'tenant_id' => $existing->tenant_id ?? Company::find($employee->company_id)?->tenant_id,
            ]);

            return;
        }

        $tenantId = Company::find($employee->company_id)?->tenant_id;

        User::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'default_company_id' => $employee->company_id,
            'name' => $employee->full_name,
            'email' => $employee->email,
            'password' => bcrypt(Str::random(16)),
        ]);
    }

    protected function enrollOnboardingCourse(Employee $employee): void
    {
        $course = Course::where('code', 'HR-ONB-01')->first();
        if (! $course) {
            return;
        }

        $class = TrainingClass::where('course_id', $course->id)
            ->whereIn('status', ['planned', 'ongoing'])
            ->orderBy('start_date')
            ->first();

        if (! $class) {
            return;
        }

        TrainingEnrollment::firstOrCreate(
            [
                'training_class_id' => $class->id,
                'employee_id' => $employee->id,
            ],
            ['status' => 'enrolled']
        );
    }
}
