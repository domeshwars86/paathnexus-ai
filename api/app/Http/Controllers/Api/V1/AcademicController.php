<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Organization;
use App\Models\OrganizationClass;
use App\Models\StudentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\PlanEntitlements;
use App\Services\TeacherClassScope;

class AcademicController extends Controller
{
    public function classes(Request $request, string $organizationId, TeacherClassScope $teacherScope): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewAcademic', $organization), 403);

        $query = $organization->classes()->where('status', 'active');
        $assignmentScopes = $teacherScope->assignmentScopes($request->user(), $organization);
        $classIds = $assignmentScopes?->pluck('class_id')->unique()->values();
        if ($classIds !== null) $query->whereIn('id', $classIds);
        $classes = $query
            ->with(['students' => function ($query) use ($assignmentScopes): void {
                $query->where('student_profiles.status', 'active')->wherePivot('status', 'active');
                if ($assignmentScopes !== null) {
                    $query->where(function ($scopeQuery) use ($assignmentScopes): void {
                        foreach ($assignmentScopes as $assignment) {
                            $scopeQuery->orWhere(function ($assignmentQuery) use ($assignment): void {
                                $assignmentQuery->where('enrollments.class_id', $assignment->class_id);
                                if ($assignment->section_id !== null) $assignmentQuery->where('enrollments.section_id', $assignment->section_id);
                            });
                        }
                    });
                }
                $query->orderBy('student_profiles.name');
            }])
            ->orderBy('name')
            ->get()
            ->map(function (OrganizationClass $class) use ($assignmentScopes): array {
                $wholeClassAssigned = $assignmentScopes === null || $assignmentScopes->contains(fn ($scope) => $scope->class_id === $class->id && $scope->section_id === null);
                $sectionIds = $wholeClassAssigned
                    ? $class->sections()->where('status', 'active')->pluck('id')->all()
                    : $assignmentScopes->where('class_id', $class->id)->whereNotNull('section_id')->pluck('section_id')->values()->all();
                $class->setAttribute('can_attend_whole', $wholeClassAssigned);
                $class->setAttribute('assigned_section_ids', $sectionIds);
                return $this->classData($class);
            });

        return response()->json(['data' => $classes]);
    }

    public function storeClass(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('organization_classes', 'name')->where('organization_id', $organization->id)],
            'grade' => ['nullable', 'string', 'max:40'],
            'section' => ['nullable', 'string', 'max:40'],
        ]);

        $class = $organization->classes()->create($validated);

        return response()->json(['data' => $this->classData($class)], 201);
    }

    public function students(Request $request, string $organizationId, TeacherClassScope $teacherScope): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewAcademic', $organization), 403);

        $query = $organization->students()->where('status', 'active');
        $studentIds = $teacherScope->assignedStudentIds($request->user(), $organization);
        if ($studentIds !== null) $query->whereIn('id', $studentIds);
        $students = $query->orderBy('name')->get(['id', 'student_code', 'name', 'status', 'created_at']);

        return response()->json(['data' => $students]);
    }

    public function storeStudent(Request $request, string $organizationId, PlanEntitlements $entitlements): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $validated = $request->validate([
            'student_code' => ['required', 'string', 'max:64', Rule::unique('student_profiles', 'student_code')->where('organization_id', $organization->id)],
            'name' => ['required', 'string', 'max:160'],
        ]);

        $student = DB::transaction(function () use ($organization, $entitlements, $validated): StudentProfile {
            $lockedOrganization = Organization::query()->whereKey($organization->id)
                ->where('status', 'active')->lockForUpdate()->firstOrFail();
            $entitlements->assertBelowLimit(
                $lockedOrganization,
                'max_students',
                $lockedOrganization->students()->where('status', 'active')->count(),
            );

            return $lockedOrganization->students()->create($validated);
        });

        return response()->json(['data' => [
            'id' => $student->id,
            'student_code' => $student->student_code,
            'name' => $student->name,
            'status' => $student->status,
        ]], 201);
    }

    public function enroll(Request $request, string $organizationId, string $classId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);

        $validated = $request->validate(['student_id' => ['required', 'uuid'], 'section_id' => ['nullable', 'uuid']]);
        $class = $organization->classes()->where('status', 'active')->whereKey($classId)->firstOrFail();
        $student = $organization->students()->where('status', 'active')->whereKey($validated['student_id'])->firstOrFail();
        $section = null;
        if (! empty($validated['section_id'])) {
            $section = $class->sections()->where('status', 'active')->whereKey($validated['section_id'])->firstOrFail();
        }

        $enrollment = Enrollment::updateOrCreate(
            ['organization_id' => $organization->id, 'class_id' => $class->id, 'student_id' => $student->id],
            ['section_id' => $section?->id, 'status' => 'active', 'enrolled_on' => Carbon::now($organization->timezone)->toDateString()],
        );

        return response()->json(['data' => [
            'id' => $enrollment->id,
            'class_id' => $class->id,
            'section_id' => $section?->id,
            'section_name' => $section?->name,
            'student' => ['id' => $student->id, 'student_code' => $student->student_code, 'name' => $student->name],
            'status' => $enrollment->status,
            'enrolled_on' => $enrollment->enrolled_on->toDateString(),
        ]], 201);
    }

    private function memberOrganization(Request $request, string $organizationId): Organization
    {
        return $request->user()->organizations()
            ->wherePivot('status', 'active')
            ->where('organizations.status', 'active')
            ->where('organizations.id', $organizationId)
            ->firstOrFail();
    }

    private function classData(OrganizationClass $class): array
    {
        return [
            'id' => $class->id,
            'name' => $class->name,
            'grade' => $class->grade,
            'section' => $class->section,
            'can_attend_whole' => $class->getAttribute('can_attend_whole') ?? true,
            'assigned_section_ids' => $class->getAttribute('assigned_section_ids') ?? [],
            'student_count' => $class->relationLoaded('students') ? $class->students->count() : 0,
            'students' => $class->relationLoaded('students') ? $class->students->map(fn (StudentProfile $student): array => [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'name' => $student->name,
                'section_id' => $student->pivot?->section_id,
            ])->values() : [],
        ];
    }
}
