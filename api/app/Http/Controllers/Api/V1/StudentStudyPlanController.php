<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\StudentStudyPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentStudyPlanController extends Controller
{
    public function index(Request $request, string $organizationId): JsonResponse
    {
        [$organization, $student] = $this->studentContext($request, $organizationId);
        $plans = StudentStudyPlan::query()->where('organization_id', $organization->id)->where('student_id', $student->id)
            ->with('course:id,title')->orderByRaw("CASE WHEN status = 'planned' THEN 0 ELSE 1 END")
            ->orderBy('scheduled_at')->limit(100)->get();

        return response()->json(['data' => $plans->map(fn (StudentStudyPlan $plan): array => $this->data($plan))]);
    }

    public function store(Request $request, string $organizationId): JsonResponse
    {
        [$organization, $student] = $this->studentContext($request, $organizationId);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:140', 'not_regex:/^\s*$/'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'course_id' => ['nullable', 'uuid'],
        ]);
        if (! empty($validated['course_id'])) {
            $enrolled = DB::table('course_enrollments')->where('organization_id', $organization->id)
                ->where('student_id', $student->id)->where('course_id', $validated['course_id'])->where('status', 'active')->exists();
            abort_unless($enrolled, 422, 'Choose a course you are actively enrolled in.');
        }

        $plan = StudentStudyPlan::create([
            'organization_id' => $organization->id, 'student_id' => $student->id,
            'course_id' => $validated['course_id'] ?? null, 'title' => trim($validated['title']),
            'scheduled_at' => $validated['scheduled_at'], 'duration_minutes' => $validated['duration_minutes'], 'status' => 'planned',
        ])->load('course:id,title');

        return response()->json(['data' => $this->data($plan)], 201);
    }

    public function complete(Request $request, string $organizationId, string $planId): JsonResponse
    {
        [$organization, $student] = $this->studentContext($request, $organizationId);
        $plan = DB::transaction(function () use ($organization, $student, $planId): StudentStudyPlan {
            $plan = StudentStudyPlan::query()->where('organization_id', $organization->id)->where('student_id', $student->id)
                ->whereKey($planId)->lockForUpdate()->firstOrFail();
            abort_unless($plan->status === 'planned', 409, 'This study session is already complete.');
            $plan->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
            return $plan->load('course:id,title');
        });

        return response()->json(['data' => $this->data($plan)]);
    }

    private function studentContext(Request $request, string $organizationId): array
    {
        $organization = $request->user()->organizations()->wherePivot('status', 'active')->where('organizations.status', 'active')
            ->where('organizations.id', $organizationId)->firstOrFail();
        abort_unless($request->user()->can('viewCourses', $organization), 403);
        $membership = $organization->users()->where('users.id', $request->user()->id)->firstOrFail();
        abort_unless($membership->pivot->role === 'student', 403);
        $student = $organization->students()->where('user_id', $request->user()->id)->where('status', 'active')->firstOrFail();

        return [$organization, $student];
    }

    private function data(StudentStudyPlan $plan): array
    {
        return [
            'id' => $plan->id, 'title' => $plan->title, 'course_id' => $plan->course_id,
            'course_title' => $plan->course?->title, 'scheduled_at' => $plan->scheduled_at?->toIso8601String(),
            'duration_minutes' => $plan->duration_minutes, 'status' => $plan->status,
            'completed_at' => $plan->completed_at?->toIso8601String(),
        ];
    }
}
