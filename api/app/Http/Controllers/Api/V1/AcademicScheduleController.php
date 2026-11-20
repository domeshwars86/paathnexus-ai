<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ClassTeacherAssignment;
use App\Models\ClassTeacherAssignmentEvent;
use App\Models\Organization;
use App\Models\OrganizationClass;
use App\Models\OrganizationSection;
use App\Models\OrganizationSubject;
use App\Models\OrganizationSubjectOffering;
use App\Models\TeacherProfile;
use App\Models\TimetableEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\TeacherClassScope;
use App\Services\PlanEntitlements;

class AcademicScheduleController extends Controller
{
    public function years(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewAcademic', $organization), 403);

        $years = $organization->academicYears()->orderByDesc('starts_on')->get()
            ->map(fn (AcademicYear $year): array => $this->yearData($year));

        return response()->json(['data' => $years]);
    }

    public function storeYear(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('academic_years', 'name')->where('organization_id', $organization->id)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'status' => ['sometimes', 'required', Rule::in(['planned', 'active', 'completed'])],
        ]);

        $year = DB::transaction(function () use ($organization, $validated): AcademicYear {
            if (($validated['status'] ?? 'planned') === 'active') {
                $organization->academicYears()->where('status', 'active')->update(['status' => 'completed']);
            }
            return $organization->academicYears()->create($validated);
        });

        return response()->json(['data' => $this->yearData($year)], 201);
    }

    public function activateYear(Request $request, string $organizationId, string $yearId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);

        $year = DB::transaction(function () use ($organization, $yearId): AcademicYear {
            $organization->newQuery()->whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $year = $organization->academicYears()->whereKey($yearId)->firstOrFail();
            abort_if($year->status === 'completed', 422, 'A completed academic year cannot be reactivated.');
            $organization->academicYears()->where('status', 'active')->where('id', '!=', $year->id)->update(['status' => 'completed']);
            $year->forceFill(['status' => 'active'])->save();
            return $year->refresh();
        });

        return response()->json(['data' => $this->yearData($year)]);
    }

    public function sections(Request $request, string $organizationId, TeacherClassScope $teacherScope): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewAcademic', $organization), 403);

        $sectionsQuery = OrganizationSection::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->with('organizationClass:id,name')
            ->orderBy('class_id')->orderBy('name');
        $scopes = $teacherScope->assignmentScopes($request->user(), $organization);
        if ($scopes !== null) {
            $wholeClassIds = $scopes->whereNull('section_id')->pluck('class_id');
            $sectionIds = $scopes->whereNotNull('section_id')->pluck('section_id');
            $sectionsQuery->where(fn ($query) => $query->whereIn('class_id', $wholeClassIds)->orWhereIn('id', $sectionIds));
        }
        $sections = $sectionsQuery->get()
            ->map(fn (OrganizationSection $section): array => $this->sectionData($section));

        return response()->json(['data' => $sections]);
    }

    public function storeSection(Request $request, string $organizationId, string $classId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $class = $organization->classes()->where('status', 'active')->whereKey($classId)->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64', Rule::unique('organization_sections', 'name')->where(fn ($query) => $query->where('organization_id', $organization->id)->where('class_id', $class->id))],
        ]);

        $section = $class->sections()->create(['organization_id' => $organization->id, ...$validated]);

        return response()->json(['data' => $this->sectionData($section->load('organizationClass:id,name'))], 201);
    }

    public function subjects(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewAcademic', $organization), 403);

        $subjects = $organization->subjects()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name', 'status']);

        return response()->json(['data' => $subjects]);
    }

    public function storeSubject(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('organization_subjects', 'code')->where('organization_id', $organization->id)],
            'name' => ['required', 'string', 'max:120', Rule::unique('organization_subjects', 'name')->where('organization_id', $organization->id)],
        ]);
        $subject = $organization->subjects()->create($validated);

        return response()->json(['data' => [
            'id' => $subject->id, 'code' => $subject->code, 'name' => $subject->name, 'status' => $subject->status,
        ]], 201);
    }

    public function teachers(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewAcademic', $organization), 403);

        $teachers = $organization->teacherProfiles()->where('status', 'active')->orderBy('name')
            ->with('user:id,name')->get(['id', 'employee_code', 'name', 'status', 'user_id', 'public_title', 'public_bio', 'is_public'])
            ->map(function (TeacherProfile $teacher) use ($request): array {
                $isMine = $teacher->user_id === $request->user()->id;
                return $this->teacherData($teacher) + [
                    'is_my_profile' => $isMine,
                    'public_title' => $isMine ? $teacher->public_title : null,
                    'public_bio' => $isMine ? $teacher->public_bio : null,
                    'is_public' => $isMine ? (bool) $teacher->is_public : null,
                ];
            });

        return response()->json(['data' => $teachers]);
    }

    public function storeTeacher(Request $request, string $organizationId, PlanEntitlements $entitlements): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $entitlements->assertBelowLimit($organization, 'max_teachers', $organization->teacherProfiles()->where('status', 'active')->count());

        $validated = $request->validate([
            'employee_code' => ['required', 'string', 'max:64', Rule::unique('teacher_profiles', 'employee_code')->where('organization_id', $organization->id)],
            'name' => ['required', 'string', 'max:160'],
        ]);
        $teacher = $organization->teacherProfiles()->create($validated);

        return response()->json(['data' => $this->teacherData($teacher)], 201);
    }

    public function teacherMemberCandidates(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);

        $linkedIds = $organization->teacherProfiles()->whereNotNull('user_id')->select('user_id');
        $candidates = $organization->users()
            ->wherePivot('status', 'active')->wherePivot('role', 'teacher')
            ->where('users.account_status', 'active')
            ->whereNotIn('users.id', $linkedIds)
            ->orderBy('users.name')->get(['users.id', 'users.name'])
            ->map(fn ($user): array => ['id' => $user->id, 'name' => $user->name]);

        return response()->json(['data' => $candidates]);
    }

    public function linkTeacherMember(Request $request, string $organizationId, string $teacherId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $validated = $request->validate(['user_id' => ['present', 'nullable', 'integer']]);
        $teacher = $organization->teacherProfiles()->whereKey($teacherId)->firstOrFail();

        if ($validated['user_id'] !== null) {
            $member = $organization->users()
                ->wherePivot('status', 'active')->wherePivot('role', 'teacher')
                ->where('users.account_status', 'active')
                ->where('users.id', $validated['user_id'])->first();
            abort_unless($member, 404);
            abort_if($organization->teacherProfiles()->where('user_id', $member->id)->where('id', '!=', $teacher->id)->exists(), 422, 'This member is already linked to a teacher profile.');
        }

        $teacher->forceFill(['user_id' => $validated['user_id']])->save();

        return response()->json(['data' => $this->teacherData($teacher->load('user:id,name'))]);
    }

    public function updatePublicProfile(Request $request, string $organizationId, string $teacherId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        $teacher = $organization->teacherProfiles()->where('status', 'active')->whereKey($teacherId)->firstOrFail();
        abort_unless($teacher->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'public_title' => ['nullable', 'string', 'max:120'],
            'public_bio' => ['nullable', 'string', 'max:1200'],
            'is_public' => ['required', 'boolean'],
        ]);
        $teacher->forceFill($validated)->save();

        $teacher = $teacher->fresh()->load('user:id,name');
        return response()->json(['data' => $this->teacherData($teacher) + [
            'public_title' => $teacher->public_title, 'public_bio' => $teacher->public_bio, 'is_public' => (bool) $teacher->is_public,
            'is_my_profile' => true,
        ]]);
    }

    public function subjectOfferings(Request $request, string $organizationId, string $classId, TeacherClassScope $teacherScope): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewAcademic', $organization), 403);
        $class = $organization->classes()->where('status', 'active')->whereKey($classId)->firstOrFail();
        $visibleSections = $this->visibleTeacherSections($request, $organization, $class, $teacherScope);
        $yearId = $request->query('academic_year_id')
            ?? $organization->academicYears()->where('status', 'active')->value('id');
        if (! $yearId) {
            return response()->json(['data' => []]);
        }
        abort_unless($organization->academicYears()->whereKey($yearId)->exists(), 404);

        $offerings = OrganizationSubjectOffering::query()
            ->where('organization_id', $organization->id)->where('class_id', $class->id)
            ->where('academic_year_id', $yearId)->whereNull('ended_at')
            ->when($visibleSections !== null, fn ($query) => $query->where(fn ($scope) => $scope->whereNull('section_id')->orWhereIn('section_id', $visibleSections)))
            ->with(['subject:id,code,name', 'section:id,name'])
            ->orderBy('section_id')->orderBy('created_at')->get()
            ->map(fn (OrganizationSubjectOffering $offering): array => $this->subjectOfferingData($offering));

        return response()->json(['data' => $offerings]);
    }

    public function storeSubjectOffering(Request $request, string $organizationId, string $classId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $validated = $request->validate([
            'academic_year_id' => ['required', 'uuid'],
            'subject_id' => ['required', 'uuid'],
            'section_id' => ['nullable', 'uuid'],
        ]);
        $class = $organization->classes()->where('status', 'active')->whereKey($classId)->firstOrFail();
        abort_unless($organization->academicYears()->whereKey($validated['academic_year_id'])->exists(), 404);
        $subject = $organization->subjects()->where('status', 'active')->whereKey($validated['subject_id'])->firstOrFail();
        $sectionId = $validated['section_id'] ?? null;
        if ($sectionId) {
            abort_unless($class->sections()->where('status', 'active')->whereKey($sectionId)->exists(), 404);
        }

        $offering = DB::transaction(function () use ($organization, $class, $validated, $sectionId, $request) {
            $organization->classes()->whereKey($class->id)->lockForUpdate()->firstOrFail();
            $duplicate = OrganizationSubjectOffering::query()
                ->where('organization_id', $organization->id)->where('class_id', $class->id)
                ->where('academic_year_id', $validated['academic_year_id'])->where('subject_id', $validated['subject_id'])
                ->whereNull('ended_at');
            $sectionId ? $duplicate->where('section_id', $sectionId) : $duplicate->whereNull('section_id');
            abort_if($duplicate->exists(), 422, 'This subject is already offered to this class or section for the academic year.');

            return OrganizationSubjectOffering::create([
                'organization_id' => $organization->id,
                'class_id' => $class->id,
                'section_id' => $sectionId,
                'academic_year_id' => $validated['academic_year_id'],
                'subject_id' => $validated['subject_id'],
                'created_by' => $request->user()->id,
            ])->load(['subject:id,code,name', 'section:id,name']);
        });

        return response()->json(['data' => $this->subjectOfferingData($offering)], 201);
    }

    public function endSubjectOffering(Request $request, string $organizationId, string $classId, string $offeringId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $class = $organization->classes()->where('status', 'active')->whereKey($classId)->firstOrFail();
        $offering = OrganizationSubjectOffering::query()->where('organization_id', $organization->id)
            ->where('class_id', $class->id)->whereNull('ended_at')->whereKey($offeringId)->firstOrFail();
        $offering->forceFill(['ended_at' => now()])->save();

        return response()->json(['data' => ['id' => $offering->id, 'status' => 'ended']]);
    }

    public function classTeachers(Request $request, string $organizationId, string $classId, TeacherClassScope $teacherScope): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewAcademic', $organization), 403);
        $class = $organization->classes()->where('status', 'active')->whereKey($classId)->firstOrFail();
        $visibleSections = $this->visibleTeacherSections($request, $organization, $class, $teacherScope);
        $yearId = $request->query('academic_year_id')
            ?? $organization->academicYears()->where('status', 'active')->value('id');
        if (! $yearId) {
            return response()->json(['data' => []]);
        }
        abort_unless($organization->academicYears()->whereKey($yearId)->exists(), 404);

        $assignments = ClassTeacherAssignment::query()
            ->where('organization_id', $organization->id)
            ->where('class_id', $class->id)
            ->where('academic_year_id', $yearId)
            ->whereNull('ended_at')
            ->when($visibleSections !== null, fn ($query) => $query->where(fn ($scope) => $scope->whereNull('section_id')->orWhereIn('section_id', $visibleSections)))
            ->with(['teacherProfile:id,name,employee_code,user_id', 'teacherProfile.user:id,name', 'section:id,name'])
            ->orderBy('section_id')->orderBy('created_at')->get()
            ->map(fn (ClassTeacherAssignment $assignment): array => $this->classTeacherData($assignment));

        return response()->json(['data' => $assignments]);
    }

    public function storeClassTeacher(Request $request, string $organizationId, string $classId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $validated = $request->validate([
            'teacher_profile_id' => ['required', 'uuid'],
            'academic_year_id' => ['required', 'uuid'],
            'section_id' => ['nullable', 'uuid'],
        ]);
        $class = $organization->classes()->where('status', 'active')->whereKey($classId)->firstOrFail();
        $teacher = $organization->teacherProfiles()->where('status', 'active')->whereKey($validated['teacher_profile_id'])->firstOrFail();
        abort_unless($organization->academicYears()->whereKey($validated['academic_year_id'])->exists(), 404);
        if (! empty($validated['section_id'])) {
            abort_unless($class->sections()->where('status', 'active')->whereKey($validated['section_id'])->exists(), 404);
        }

        $sectionId = $validated['section_id'] ?? null;
        $assignment = DB::transaction(function () use ($organization, $class, $validated, $sectionId, $teacher, $request) {
            $organization->classes()->whereKey($class->id)->lockForUpdate()->firstOrFail();
            $duplicate = ClassTeacherAssignment::query()
                ->where('organization_id', $organization->id)->where('class_id', $class->id)
                ->where('academic_year_id', $validated['academic_year_id'])->whereNull('ended_at');
            $sectionId ? $duplicate->where('section_id', $sectionId) : $duplicate->whereNull('section_id');
            abort_if($duplicate->exists(), 422, 'This class or section already has a class teacher for the academic year.');

            $assignment = ClassTeacherAssignment::create([
                ...$validated,
                'organization_id' => $organization->id,
                'class_id' => $class->id,
                'teacher_profile_id' => $teacher->id,
                'assigned_by' => $request->user()->id,
            ]);
            $this->recordTeacherAssignmentEvent($assignment, 'assigned', null, $teacher->id, $request->user()->id);

            return $assignment->load(['teacherProfile:id,name,employee_code,user_id', 'teacherProfile.user:id,name', 'section:id,name']);
        });

        return response()->json(['data' => $this->classTeacherData($assignment)], 201);
    }

    public function updateClassTeacher(Request $request, string $organizationId, string $classId, string $assignmentId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $validated = $request->validate(['teacher_profile_id' => ['required', 'uuid']]);
        $class = $organization->classes()->where('status', 'active')->whereKey($classId)->firstOrFail();
        $teacher = $organization->teacherProfiles()->where('status', 'active')->whereKey($validated['teacher_profile_id'])->firstOrFail();

        $assignment = DB::transaction(function () use ($organization, $class, $assignmentId, $teacher, $request) {
            $organization->classes()->whereKey($class->id)->lockForUpdate()->firstOrFail();
            $assignment = ClassTeacherAssignment::query()->where('organization_id', $organization->id)
                ->where('class_id', $class->id)->whereNull('ended_at')->whereKey($assignmentId)->lockForUpdate()->firstOrFail();
            $previousTeacherId = $assignment->teacher_profile_id;
            if ($previousTeacherId !== $teacher->id) {
                $assignment->forceFill(['teacher_profile_id' => $teacher->id])->save();
                $this->recordTeacherAssignmentEvent($assignment, 'reassigned', $previousTeacherId, $teacher->id, $request->user()->id);
            }

            return $assignment->refresh()->load(['teacherProfile:id,name,employee_code,user_id', 'teacherProfile.user:id,name', 'section:id,name']);
        });

        return response()->json(['data' => $this->classTeacherData($assignment)]);
    }

    public function endClassTeacher(Request $request, string $organizationId, string $classId, string $assignmentId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $class = $organization->classes()->where('status', 'active')->whereKey($classId)->firstOrFail();
        $assignment = DB::transaction(function () use ($organization, $class, $assignmentId, $request) {
            $organization->classes()->whereKey($class->id)->lockForUpdate()->firstOrFail();
            $assignment = ClassTeacherAssignment::query()->where('organization_id', $organization->id)
                ->where('class_id', $class->id)->whereNull('ended_at')->whereKey($assignmentId)->lockForUpdate()->firstOrFail();
            $teacherId = $assignment->teacher_profile_id;
            $assignment->forceFill(['ended_at' => now()])->save();
            $this->recordTeacherAssignmentEvent($assignment, 'removed', $teacherId, null, $request->user()->id);
            return $assignment;
        });

        return response()->json(['data' => ['id' => $assignment->id, 'status' => 'ended']]);
    }

    public function classTeacherHistory(Request $request, string $organizationId, string $classId, TeacherClassScope $teacherScope): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewAcademic', $organization), 403);
        $class = $organization->classes()->where('status', 'active')->whereKey($classId)->firstOrFail();
        $visibleSections = $this->visibleTeacherSections($request, $organization, $class, $teacherScope);
        $validated = $request->validate(['academic_year_id' => ['sometimes', 'uuid']]);
        $yearId = $validated['academic_year_id'] ?? $organization->academicYears()->where('status', 'active')->value('id');
        if (! $yearId) {
            return response()->json(['data' => []]);
        }
        abort_unless($organization->academicYears()->whereKey($yearId)->exists(), 404);
        $events = ClassTeacherAssignmentEvent::query()->where('organization_id', $organization->id)
            ->whereHas('assignment', fn ($query) => $query->where('class_id', $class->id)->where('academic_year_id', $yearId)
                ->when($visibleSections !== null, fn ($scope) => $scope->where(fn ($sectionScope) => $sectionScope->whereNull('section_id')->orWhereIn('section_id', $visibleSections))))
            ->with(['fromTeacher:id,name', 'toTeacher:id,name', 'actor:id,name', 'assignment.section:id,name'])
            ->latest()->limit(50)->get()
            ->map(fn (ClassTeacherAssignmentEvent $event): array => [
                'id' => $event->id,
                'assignment_id' => $event->assignment_id,
                'action' => $event->action,
                'from_teacher' => $event->fromTeacher?->name,
                'to_teacher' => $event->toTeacher?->name,
                'section_name' => $event->assignment?->section?->name,
                'actor_name' => $event->actor?->name,
                'created_at' => $event->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $events]);
    }

    public function timetable(Request $request, string $organizationId, TeacherClassScope $teacherScope): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewAcademic', $organization), 403);

        $validated = $request->validate([
            'academic_year_id' => ['sometimes', 'uuid'],
            'weekday' => ['sometimes', 'integer', 'between:1,7'],
        ]);
        $yearId = $validated['academic_year_id'] ?? $organization->academicYears()->where('status', 'active')->value('id');
        $query = $organization->timetableEntries()->with(['organizationClass:id,name', 'section:id,name', 'academicYear:id,name', 'subjectCatalog:id,code,name', 'teacherProfile:id,name,employee_code']);
        if ($yearId) {
            abort_unless($organization->academicYears()->whereKey($yearId)->exists(), 404);
            $query->where('academic_year_id', $yearId);
        } else {
            return response()->json(['data' => []]);
        }
        if (isset($validated['weekday'])) {
            $query->where('weekday', $validated['weekday']);
        }
        $assignmentScopes = $teacherScope->assignmentScopes($request->user(), $organization);
        if ($assignmentScopes !== null) {
            if ($assignmentScopes->isEmpty()) $query->whereRaw('1 = 0');
            else $query->where(function ($scopeQuery) use ($assignmentScopes): void {
                    foreach ($assignmentScopes as $assignment) {
                        $scopeQuery->orWhere(function ($assignmentQuery) use ($assignment): void {
                            $assignmentQuery->where('class_id', $assignment->class_id);
                            if ($assignment->section_id !== null) {
                                $assignmentQuery->where(fn ($sectionQuery) => $sectionQuery->where('section_id', $assignment->section_id)->orWhereNull('section_id'));
                            }
                        });
                    }
                });
        }

        $entries = $query->orderBy('weekday')->orderBy('starts_at')->get()
            ->map(fn (TimetableEntry $entry): array => $this->entryData($entry));

        return response()->json(['data' => $entries]);
    }

    public function storeTimetableEntry(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $entry = $this->saveTimetableEntry($request, $organization);
        return response()->json(['data' => $this->entryData($entry)], 201);
    }

    public function updateTimetableEntry(Request $request, string $organizationId, string $entryId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $entry = $organization->timetableEntries()->whereKey($entryId)->firstOrFail();
        $entry = $this->saveTimetableEntry($request, $organization, $entry);
        return response()->json(['data' => $this->entryData($entry)]);
    }

    private function saveTimetableEntry(Request $request, Organization $organization, ?TimetableEntry $entry = null): TimetableEntry
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'uuid'],
            'class_id' => ['required', 'uuid'],
            'section_id' => ['nullable', 'uuid'],
            'subject_id' => ['nullable', 'uuid', 'required_without:subject'],
            'subject' => ['nullable', 'string', 'max:120', 'required_without:subject_id'],
            'teacher_profile_id' => ['nullable', 'uuid'],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'room' => ['nullable', 'string', 'max:80'],
        ]);

        $class = $organization->classes()->where('status', 'active')->whereKey($validated['class_id'])->firstOrFail();
        abort_unless($organization->academicYears()->whereKey($validated['academic_year_id'])->exists(), 404);
        $subject = ! empty($validated['subject_id'])
            ? $organization->subjects()->where('status', 'active')->whereKey($validated['subject_id'])->firstOrFail()
            : null;
        $teacher = ! empty($validated['teacher_profile_id'])
            ? $organization->teacherProfiles()->where('status', 'active')->whereKey($validated['teacher_profile_id'])->firstOrFail()
            : null;
        if (! empty($validated['section_id'])) {
            abort_unless($class->sections()->where('status', 'active')->whereKey($validated['section_id'])->exists(), 404);
        }

        if ($subject) {
            $classHasOfferings = OrganizationSubjectOffering::query()
                ->where('organization_id', $organization->id)->where('class_id', $class->id)
                ->where('academic_year_id', $validated['academic_year_id'])->whereNull('ended_at')->exists();
            if ($classHasOfferings) {
                $matchingOffering = OrganizationSubjectOffering::query()
                    ->where('organization_id', $organization->id)->where('class_id', $class->id)
                    ->where('academic_year_id', $validated['academic_year_id'])->where('subject_id', $subject->id)
                    ->whereNull('ended_at')
                    ->where(function ($query) use ($validated): void {
                        $query->whereNull('section_id');
                        if (! empty($validated['section_id'])) $query->orWhere('section_id', $validated['section_id']);
                    })->exists();
                abort_unless($matchingOffering, 422, 'This subject is not offered to the selected class or section for the academic year.');
            }
        }

        $overlap = $organization->timetableEntries()
            ->where('academic_year_id', $validated['academic_year_id'])->where('class_id', $class->id)
            ->where('weekday', $validated['weekday'])
            ->where('starts_at', '<', $validated['ends_at'])->where('ends_at', '>', $validated['starts_at']);
        if (! empty($validated['section_id'])) {
            $overlap->where(fn ($query) => $query->whereNull('section_id')->orWhere('section_id', $validated['section_id']));
        }
        if ($entry) $overlap->where('id', '<>', $entry->id);
        abort_if($overlap->exists(), 422, 'This class already has a lesson scheduled in that time slot.');

        if ($teacher) {
            $teacherOverlap = $organization->timetableEntries()
                ->where('academic_year_id', $validated['academic_year_id'])->where('teacher_profile_id', $teacher->id)
                ->where('weekday', $validated['weekday'])
                ->where('starts_at', '<', $validated['ends_at'])->where('ends_at', '>', $validated['starts_at']);
            if ($entry) $teacherOverlap->where('id', '<>', $entry->id);
            abort_if($teacherOverlap->exists(), 422, 'This teacher is already scheduled in that time slot.');
        }

        $values = [
            ...$validated,
            'subject' => $subject?->name ?? trim($validated['subject'] ?? ''),
            'subject_id' => $subject?->id,
            'teacher_profile_id' => $teacher?->id,
        ];
        if ($entry) {
            $entry->update($values);
        } else {
            $entry = $organization->timetableEntries()->create($values);
        }

        return $entry->load(['organizationClass:id,name', 'section:id,name', 'academicYear:id,name', 'subjectCatalog:id,code,name', 'teacherProfile:id,name,employee_code']);
    }

    private function memberOrganization(Request $request, string $organizationId): Organization
    {
        return $request->user()->organizations()
            ->wherePivot('status', 'active')
            ->where('organizations.status', 'active')
            ->where('organizations.id', $organizationId)
            ->firstOrFail();
    }

    /** Null means unrestricted; section IDs limit section assignments while retaining class-wide rows. */
    private function visibleTeacherSections(Request $request, Organization $organization, OrganizationClass $class, TeacherClassScope $teacherScope): ?Collection
    {
        $scopes = $teacherScope->assignmentScopes($request->user(), $organization);
        if ($scopes === null) return null;
        $classScopes = $scopes->where('class_id', $class->id);
        abort_if($classScopes->isEmpty(), 404);
        if ($classScopes->contains(fn ($scope): bool => $scope->section_id === null)) return null;
        return $classScopes->pluck('section_id')->unique()->values();
    }

    private function yearData(AcademicYear $year): array
    {
        return [
            'id' => $year->id,
            'name' => $year->name,
            'starts_on' => $year->starts_on->toDateString(),
            'ends_on' => $year->ends_on->toDateString(),
            'status' => $year->status,
        ];
    }

    private function sectionData(OrganizationSection $section): array
    {
        return [
            'id' => $section->id,
            'class_id' => $section->class_id,
            'class_name' => $section->organizationClass?->name,
            'name' => $section->name,
        ];
    }

    private function teacherData(TeacherProfile $teacher): array
    {
        return [
            'id' => $teacher->id,
            'employee_code' => $teacher->employee_code,
            'name' => $teacher->name,
            'status' => $teacher->status,
            'user_id' => $teacher->user_id,
            'member_name' => $teacher->user?->name,
        ];
    }

    private function classTeacherData(ClassTeacherAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'class_id' => $assignment->class_id,
            'teacher_profile_id' => $assignment->teacher_profile_id,
            'teacher_name' => $assignment->teacherProfile?->name,
            'member_name' => $assignment->teacherProfile?->user?->name,
            'employee_code' => $assignment->teacherProfile?->employee_code,
            'academic_year_id' => $assignment->academic_year_id,
            'section_id' => $assignment->section_id,
            'section_name' => $assignment->section?->name,
        ];
    }

    private function recordTeacherAssignmentEvent(
        ClassTeacherAssignment $assignment,
        string $action,
        ?string $fromTeacherId,
        ?string $toTeacherId,
        int $actorId,
    ): void {
        ClassTeacherAssignmentEvent::create([
            'organization_id' => $assignment->organization_id,
            'assignment_id' => $assignment->id,
            'action' => $action,
            'from_teacher_profile_id' => $fromTeacherId,
            'to_teacher_profile_id' => $toTeacherId,
            'actor_id' => $actorId,
        ]);
    }

    private function subjectOfferingData(OrganizationSubjectOffering $offering): array
    {
        return [
            'id' => $offering->id,
            'class_id' => $offering->class_id,
            'academic_year_id' => $offering->academic_year_id,
            'section_id' => $offering->section_id,
            'section_name' => $offering->section?->name,
            'subject_id' => $offering->subject_id,
            'subject_code' => $offering->subject?->code,
            'subject_name' => $offering->subject?->name,
        ];
    }

    private function entryData(TimetableEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'academic_year_id' => $entry->academic_year_id,
            'academic_year' => $entry->academicYear?->name,
            'class_id' => $entry->class_id,
            'class_name' => $entry->organizationClass?->name,
            'section_id' => $entry->section_id,
            'section_name' => $entry->section?->name,
            'subject' => $entry->subject,
            'subject_id' => $entry->subject_id,
            'teacher_profile_id' => $entry->teacher_profile_id,
            'teacher_name' => $entry->teacherProfile?->name,
            'weekday' => $entry->weekday,
            'starts_at' => substr((string) $entry->starts_at, 0, 5),
            'ends_at' => substr((string) $entry->ends_at, 0, 5),
            'room' => $entry->room,
        ];
    }
}
