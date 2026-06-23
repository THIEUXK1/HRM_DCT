<?php

namespace App\Http\Controllers\Api;

use App\Models\Course;
use App\Models\CourseCompetency;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Course::with(['category', 'classes'])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->q.'%'))
            ->orderBy('name');

        return $this->success($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'type' => 'nullable|string',
            'duration_hours' => 'integer|min:0',
            'course_category_id' => 'nullable|exists:course_categories,id',
        ]);

        $data['tenant_id'] = CompanyContext::tenantId();
        if (! $data['tenant_id']) {
            return $this->error('Thiếu tenant context, vui lòng chọn công ty hợp lệ', 422);
        }

        return $this->success(Course::create($data), 201);
    }

    public function competencies(Course $course): JsonResponse
    {
        return $this->success(
            $course->courseCompetencies()->with('competency')->get()
        );
    }

    public function syncCompetencies(Request $request, Course $course): JsonResponse
    {
        $data = $request->validate([
            'mappings' => 'required|array',
            'mappings.*.competency_id' => 'required|exists:competencies,id',
            'mappings.*.granted_level' => 'required|integer|min:1|max:5',
            'mappings.*.min_score' => 'nullable|numeric|min:0|max:100',
        ]);

        CourseCompetency::query()->where('course_id', $course->id)->delete();

        foreach ($data['mappings'] as $row) {
            CourseCompetency::create([
                'course_id' => $course->id,
                'competency_id' => $row['competency_id'],
                'granted_level' => $row['granted_level'],
                'min_score' => $row['min_score'] ?? 0,
            ]);
        }

        return $this->success($course->courseCompetencies()->with('competency')->get());
    }
}
