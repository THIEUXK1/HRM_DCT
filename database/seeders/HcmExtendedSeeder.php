<?php

namespace Database\Seeders;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Competency;
use App\Models\CompetencyGroup;
use App\Models\Position;
use App\Models\PositionCompetencyRequirement;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseCompetency;
use App\Models\PerformanceCycle;
use App\Models\Tenant;
use App\Models\TrainingClass;
use Illuminate\Database\Seeder;

class HcmExtendedSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();
        if (! $tenant) {
            return;
        }

        foreach ([
            ['code' => 'recruitment_request', 'name' => 'Duyệt yêu cầu tuyển dụng', 'entity_type' => 'recruitment_request', 'steps' => [
                ['order' => 1, 'role' => 'department_manager', 'label' => 'Trưởng bộ phận'],
                ['order' => 2, 'role' => 'hr_manager', 'label' => 'HR / Ban giám đốc'],
            ]],
            ['code' => 'leave_request', 'name' => 'Duyệt nghỉ phép', 'entity_type' => 'leave_request', 'steps' => [
                ['order' => 1, 'role' => 'department_manager', 'label' => 'Quản lý trực tiếp'],
                ['order' => 2, 'role' => 'hr_manager', 'label' => 'HR xác nhận'],
            ]],
            ['code' => 'overtime_request', 'name' => 'Duyệt tăng ca', 'entity_type' => 'overtime_request', 'steps' => [
                ['order' => 1, 'role' => 'department_manager', 'label' => 'Quản lý duyệt'],
                ['order' => 2, 'role' => 'hr_manager', 'label' => 'HR duyệt'],
            ]],
        ] as $wf) {
            $workflow = ApprovalWorkflow::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $wf['code']],
                ['name' => $wf['name'], 'entity_type' => $wf['entity_type'], 'is_active' => true]
            );
            foreach ($wf['steps'] as $step) {
                ApprovalStep::firstOrCreate(
                    [
                        'approval_workflow_id' => $workflow->id,
                        'step_order' => $step['order'],
                    ],
                    ['approver_role' => $step['role'], 'label' => $step['label']]
                );
            }
        }

        $cat = CourseCategory::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'ONBOARD'],
            ['name' => 'Đào tạo hội nhập']
        );

        $course = Course::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'HR-ONB-01'],
            [
                'course_category_id' => $cat->id,
                'name' => 'Giới thiệu công ty & quy trình',
                'type' => 'mandatory',
                'duration_hours' => 8,
            ]
        );

        TrainingClass::firstOrCreate(
            ['course_id' => $course->id, 'name' => 'Lớp hội nhập Q2/2026'],
            ['start_date' => now()->addDays(7), 'status' => 'planned']
        );

        $group = CompetencyGroup::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Kỹ năng mềm'],
        );

        $competencies = [];
        foreach ([
            ['code' => 'COMM', 'name' => 'Giao tiếp'],
            ['code' => 'LEAD', 'name' => 'Lãnh đạo'],
            ['code' => 'PROB', 'name' => 'Giải quyết vấn đề'],
        ] as $c) {
            $competencies[] = Competency::firstOrCreate(
                ['competency_group_id' => $group->id, 'code' => $c['code']],
                ['name' => $c['name'], 'max_level' => 5]
            );
        }

        $comm = collect($competencies)->first(fn ($c) => $c->code === 'COMM');
        if ($comm) {
            CourseCompetency::firstOrCreate(
                ['course_id' => $course->id, 'competency_id' => $comm->id],
                ['granted_level' => 3, 'min_score' => 70]
            );
        }

        $position = Position::first();
        if ($position) {
            foreach ($competencies as $i => $competency) {
                PositionCompetencyRequirement::firstOrCreate(
                    [
                        'position_id' => $position->id,
                        'competency_id' => $competency->id,
                    ],
                    ['required_level' => min(5, 3 + ($i % 2))]
                );
            }
        }

        PerformanceCycle::firstOrCreate(
            ['tenant_id' => $tenant->id, 'period' => now()->format('Y')],
            [
                'name' => 'Đánh giá năm '.now()->format('Y'),
                'start_date' => now()->startOfYear(),
                'end_date' => now()->endOfYear(),
                'status' => 'active',
            ]
        );
    }
}
