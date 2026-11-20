<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseCertificate;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\Assignment;
use App\Models\AssignmentAttachment;
use App\Models\AssignmentSubmission;
use App\Models\LessonProgress;
use App\Models\Organization;
use App\Models\StudentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Services\PlanEntitlements;
use App\Services\TeacherCourseScope;
use App\Services\TeacherClassScope;

class LearningController extends Controller
{
    public function courses(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewCourses', $organization) || $request->user()->can('manageCourses', $organization), 403);
        $canManage = $request->user()->can('manageCourses', $organization);
        $teacherStudentIds = app(TeacherClassScope::class)->assignedStudentIds($request->user(), $organization);
        $isTeacher = $teacherStudentIds !== null;
        $query = $this->scopedCourses($organization, $request)->whereIn('status', $canManage ? ['active', 'draft'] : ['active'])
            ->with(['subject:id,code,name', 'academicYear:id,name', 'instructors:id,name'])
            ->withCount([
                'lessons' => fn ($lessons) => $lessons->where('status', 'active'),
                'enrollments' => function ($enrollments) use ($teacherStudentIds): void {
                    $enrollments->where('status', 'active');
                    if ($teacherStudentIds !== null) $enrollments->whereIn('student_id', $teacherStudentIds);
                },
            ])
            ->orderBy('title');

        if (! $canManage) {
            $student = $this->linkedStudent($request, $organization);
            if (! $student && ! $isTeacher) {
                return response()->json(['data' => []]);
            }
            if ($student) $query->whereHas('enrollments', fn ($enrollments) => $enrollments
                ->where('student_id', $student->id)->where('status', 'active'));
        }

        return response()->json(['data' => $query->get()->map(fn (Course $course): array => $this->courseData($course))]);
    }

    public function storeCourse(Request $request, string $organizationId, PlanEntitlements $entitlements): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageCourses', $organization), 403);
        $entitlements->assertBelowLimit($organization, 'max_courses', $organization->courses()->whereIn('status', ['active', 'draft'])->count());
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique('courses', 'code')->where('organization_id', $organization->id)],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price_paise' => ['sometimes', 'integer', 'min:0', 'max:1000000000'],
            'academic_year_id' => ['nullable', 'uuid'],
            'subject_id' => ['nullable', 'uuid'],
        ]);
        if (! empty($validated['academic_year_id'])) {
            abort_unless($organization->academicYears()->whereKey($validated['academic_year_id'])->exists(), 404);
        }
        if (! empty($validated['subject_id'])) {
            abort_unless($organization->subjects()->where('status', 'active')->whereKey($validated['subject_id'])->exists(), 404);
        }
        $course = $organization->courses()->create([...$validated, 'created_by' => $request->user()->id]);

        return response()->json(['data' => $this->courseData($course->load(['subject:id,code,name', 'academicYear:id,name']))], 201);
    }

    public function updateCourse(Request $request, string $organizationId, string $courseId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageCourses', $organization), 403);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['active', 'draft'])],
            'is_public' => ['sometimes', 'boolean'],
            'price_paise' => ['sometimes', 'integer', 'min:0', 'max:1000000000'],
        ]);
        $course = $this->scopedCourses($organization, $request)->whereIn('status', ['active', 'draft'])->whereKey($courseId)->firstOrFail();
        $course->fill($validated)->save();
        return response()->json(['data' => $this->courseData($course->fresh()->load(['subject:id,code,name', 'academicYear:id,name']))]);
    }

    public function courseInstructorCandidates(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        $this->assertCourseAssignmentManager($request, $organization);
        $teachers = $organization->users()->wherePivot('status', 'active')->wherePivot('role', 'teacher')
            ->orderBy('users.name')->get(['users.id', 'users.name']);

        return response()->json(['data' => $teachers->map(fn ($teacher): array => [
            'id' => $teacher->id, 'name' => $teacher->name,
        ])]);
    }

    public function updateCourseInstructors(Request $request, string $organizationId, string $courseId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        $this->assertCourseAssignmentManager($request, $organization);
        $validated = $request->validate(['instructor_ids' => ['required', 'array', 'max:30'], 'instructor_ids.*' => ['required', 'integer', 'distinct']]);
        $instructorIds = $validated['instructor_ids'];
        $validTeacherIds = $organization->users()->wherePivot('status', 'active')->wherePivot('role', 'teacher')
            ->whereIn('users.id', $instructorIds)->pluck('users.id')->map(fn ($id): int => (int) $id)->all();
        abort_unless(count($validTeacherIds) === count($instructorIds), 422, 'Every assigned instructor must be an active teacher in this workspace.');

        $course = $this->scopedCourses($organization, $request)->whereIn('status', ['active', 'draft'])->whereKey($courseId)->firstOrFail();
        DB::transaction(function () use ($course, $organization, $validTeacherIds, $request): void {
            $course->instructors()->wherePivot('organization_id', $organization->id)->detach();
            if ($validTeacherIds) {
                $course->instructors()->attach(array_fill_keys($validTeacherIds, [
                    'organization_id' => $organization->id, 'assigned_by' => $request->user()->id,
                ]));
            }
        });

        return response()->json(['data' => $this->courseData($course->fresh()->load(['subject:id,code,name', 'academicYear:id,name', 'instructors:id,name']))]);
    }

    private function assertCourseAssignmentManager(Request $request, Organization $organization): void
    {
        abort_unless($request->user()->can('manageCourses', $organization), 403);
    }

    public function lessons(Request $request, string $organizationId, string $courseId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewCourses', $organization) || $request->user()->can('manageCourses', $organization), 403);
        $student = null;
        $canManage = $request->user()->can('manageCourses', $organization);
        $isTeacher = app(TeacherClassScope::class)->assignedStudentIds($request->user(), $organization) !== null;
        $course = $this->scopedCourses($organization, $request)->whereIn('status', $canManage ? ['active', 'draft'] : ['active'])->whereKey($courseId)->firstOrFail();
        if (! $canManage && ! $isTeacher) {
            $student = $this->linkedStudent($request, $organization);
            abort_unless($student && $course->enrollments()->where('student_id', $student->id)->where('status', 'active')->exists(), 404);
        }
        $lessons = $course->lessons()->whereIn('status', $student ? ['active'] : ['active', 'archived'])->get();
        $progress = $student ? LessonProgress::query()->where('organization_id', $organization->id)
            ->where('course_id', $course->id)->where('student_id', $student->id)->whereIn('lesson_id', $lessons->pluck('id'))
            ->get()->keyBy('lesson_id') : collect();
        $prerequisiteRows = DB::table('course_lesson_prerequisites as prerequisite')
            ->join('course_lessons as required', function ($join): void {
                $join->on('required.organization_id', '=', 'prerequisite.organization_id')
                    ->on('required.course_id', '=', 'prerequisite.course_id')->on('required.id', '=', 'prerequisite.prerequisite_lesson_id');
            })->where('prerequisite.organization_id', $organization->id)->where('prerequisite.course_id', $course->id)
            ->whereIn('prerequisite.lesson_id', $lessons->pluck('id'))->where('required.status', 'active')
            ->orderBy('required.position')->get(['prerequisite.lesson_id', 'required.id', 'required.title'])->groupBy('lesson_id');

        return response()->json(['data' => $lessons->map(fn (CourseLesson $lesson): array => [
            'id' => $lesson->id,
            'course_id' => $lesson->course_id,
            'title' => $lesson->title,
            'content' => $lesson->content,
            'resource_type' => $lesson->resource_type,
            'resource_url' => $lesson->resource_url,
            'position' => $lesson->position,
            'status' => $lesson->status,
            'prerequisites' => ($prerequisiteRows->get($lesson->id) ?? collect())->map(fn ($required): array => ['id' => $required->id, 'title' => $required->title])->values()->all(),
            'prerequisites_met' => ! $student || ($prerequisiteRows->get($lesson->id) ?? collect())->every(fn ($required): bool => $progress->get($required->id)?->status === 'completed'),
            'my_progress' => $student ? ($progress->get($lesson->id)?->status ?? 'not_started') : null,
        ])]);
    }

    public function storeLesson(Request $request, string $organizationId, string $courseId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageCourses', $organization), 403);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'content' => ['nullable', 'string', 'max:20000'],
            'resource_url' => ['nullable', 'required_with:resource_type', 'url', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== null && parse_url($value, PHP_URL_SCHEME) !== 'https') $fail('Lesson resources must use a secure HTTPS URL.');
            }],
            'resource_type' => ['nullable', Rule::in(['video', 'document']), 'required_with:resource_url'],
        ]);
        $course = $this->scopedCourses($organization, $request)->whereIn('status', ['active', 'draft'])->whereKey($courseId)->firstOrFail();
        $lesson = DB::transaction(function () use ($course, $organization, $request, $validated) {
            $organization->courses()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            $position = ((int) $course->lessons()->max('position')) + 1;
            return $course->lessons()->create([
                'organization_id' => $organization->id,
                ...$validated,
                'position' => $position,
                'created_by' => $request->user()->id,
            ]);
        });

        return response()->json(['data' => [
            'id' => $lesson->id, 'course_id' => $lesson->course_id, 'title' => $lesson->title,
            'content' => $lesson->content, 'position' => $lesson->position, 'status' => $lesson->status, 'my_progress' => null,
            'resource_type' => $lesson->resource_type, 'resource_url' => $lesson->resource_url,
            'prerequisites' => [], 'prerequisites_met' => true,
        ]], 201);
    }

    public function updateLesson(Request $request, string $organizationId, string $courseId, string $lessonId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageCourses', $organization), 403);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'], 'content' => ['nullable', 'string', 'max:20000'],
            'resource_url' => ['nullable', 'required_with:resource_type', 'url', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== null && parse_url($value, PHP_URL_SCHEME) !== 'https') $fail('Lesson resources must use a secure HTTPS URL.');
            }],
            'resource_type' => ['nullable', Rule::in(['video', 'document']), 'required_with:resource_url'],
            'prerequisite_ids' => ['sometimes', 'array', 'max:30'],
            'prerequisite_ids.*' => ['required', 'uuid', 'distinct'],
            'status' => ['required', Rule::in(['active', 'archived'])],
        ]);
        $course = $this->scopedCourses($organization, $request)->whereIn('status', ['active', 'draft'])->whereKey($courseId)->firstOrFail();
        $lesson = $course->lessons()->whereIn('status', ['active', 'archived'])->whereKey($lessonId)->firstOrFail();
        $prerequisiteIds = $validated['prerequisite_ids'] ?? null;
        unset($validated['prerequisite_ids']);
        if ($prerequisiteIds !== null) {
            $eligiblePrerequisiteIds = $course->lessons()->where('status', 'active')->where('position', '<', $lesson->position)
                ->whereIn('id', $prerequisiteIds)->pluck('id')->all();
            abort_unless(count($eligiblePrerequisiteIds) === count($prerequisiteIds), 422, 'Prerequisites must be earlier active lessons in this course.');
        }
        DB::transaction(function () use ($lesson, $validated, $prerequisiteIds, $organization, $course): void {
            $lesson->fill($validated)->save();
            if ($prerequisiteIds !== null) {
                DB::table('course_lesson_prerequisites')->where('organization_id', $organization->id)->where('lesson_id', $lesson->id)->delete();
                foreach ($prerequisiteIds as $prerequisiteId) {
                    DB::table('course_lesson_prerequisites')->insert([
                        'organization_id' => $organization->id, 'course_id' => $course->id,
                        'lesson_id' => $lesson->id, 'prerequisite_lesson_id' => $prerequisiteId,
                    ]);
                }
            }
        });
        return response()->json(['data' => [
            'id' => $lesson->id, 'course_id' => $lesson->course_id, 'title' => $lesson->title,
            'content' => $lesson->content, 'position' => $lesson->position, 'status' => $lesson->status, 'my_progress' => null,
            'resource_type' => $lesson->resource_type, 'resource_url' => $lesson->resource_url,
            'prerequisites' => DB::table('course_lesson_prerequisites as prerequisite')->join('course_lessons as required', 'required.id', '=', 'prerequisite.prerequisite_lesson_id')
                ->where('prerequisite.organization_id', $organization->id)->where('prerequisite.course_id', $course->id)->where('prerequisite.lesson_id', $lesson->id)
                ->orderBy('required.position')->get(['required.id', 'required.title'])->map(fn ($required): array => ['id' => $required->id, 'title' => $required->title])->all(),
            'prerequisites_met' => true,
        ]]);
    }

    public function enrollments(Request $request, string $organizationId, string $courseId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageCourses', $organization), 403);
        $course = $this->scopedCourses($organization, $request)->whereKey($courseId)->firstOrFail();
        $enrollmentsQuery = $course->enrollments()->where('status', 'active')->with('student:id,student_code,name')
            ->orderBy('created_at');
        if (($studentIds = $this->scopedTeacherStudentIds($organization, $request)) !== null) {
            $enrollmentsQuery->whereIn('student_id', $studentIds);
        }
        $enrollments = $enrollmentsQuery->get();

        return response()->json(['data' => $enrollments->map(fn (CourseEnrollment $enrollment): array => [
            'id' => $enrollment->id, 'student_id' => $enrollment->student_id,
            'student_code' => $enrollment->student?->student_code, 'student_name' => $enrollment->student?->name,
            'status' => $enrollment->status, 'enrolled_at' => $enrollment->enrolled_at?->toIso8601String(),
        ])]);
    }

    public function enrollStudent(Request $request, string $organizationId, string $courseId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageCourses', $organization), 403);
        $validated = $request->validate(['student_id' => ['required', 'uuid']]);
        $course = $this->scopedCourses($organization, $request)->whereIn('status', ['active', 'draft'])->whereKey($courseId)->firstOrFail();
        $student = $organization->students()->where('status', 'active')->whereKey($validated['student_id'])->firstOrFail();
        $scopedStudentIds = $this->scopedTeacherStudentIds($organization, $request);
        abort_if($scopedStudentIds !== null && ! $scopedStudentIds->contains($student->id), 404);
        $enrollment = DB::transaction(function () use ($organization, $course, $student, $request) {
            $organization->courses()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            $existing = CourseEnrollment::query()->where('organization_id', $organization->id)
                ->where('course_id', $course->id)->where('student_id', $student->id)->lockForUpdate()->first();
            abort_if($existing?->status === 'active', 422, 'This learner is already enrolled in the course.');
            if ($existing) {
                $existing->forceFill(['status' => 'active', 'enrolled_by' => $request->user()->id, 'enrolled_at' => now()])->save();
                return $existing->refresh();
            }
            return CourseEnrollment::create([
                'organization_id' => $organization->id, 'course_id' => $course->id,
                'student_id' => $student->id, 'status' => 'active',
                'enrolled_by' => $request->user()->id, 'enrolled_at' => now(),
            ]);
        });

        return response()->json(['data' => [
            'id' => $enrollment->id, 'course_id' => $course->id, 'student_id' => $student->id,
            'student_name' => $student->name, 'status' => $enrollment->status,
        ]], 201);
    }

    public function progress(Request $request, string $organizationId, string $courseId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewCourses', $organization) || $request->user()->can('manageCourses', $organization), 403);
        $course = $this->scopedCourses($organization, $request)->whereIn('status', $request->user()->can('manageCourses', $organization) ? ['active', 'draft'] : ['active'])->whereKey($courseId)->firstOrFail();
        $query = $course->enrollments()->where('status', 'active')->with('student:id,student_code,name');
        if (($studentIds = $this->scopedTeacherStudentIds($organization, $request)) !== null) {
            $query->whereIn('student_id', $studentIds);
        }
        if (! $request->user()->can('manageCourses', $organization)) {
            $student = $this->linkedStudent($request, $organization);
            abort_unless($student, 404);
            $query->where('student_id', $student->id);
        }
        $lessonCount = $course->lessons()->where('status', 'active')->count();
        $rows = $query->get()->map(function (CourseEnrollment $enrollment) use ($organization, $course, $lessonCount): array {
            $completedCount = LessonProgress::query()->where('organization_id', $organization->id)
                ->where('course_id', $course->id)->where('student_id', $enrollment->student_id)->where('status', 'completed')->count();
            return [
                'student_id' => $enrollment->student_id,
                'student_code' => $enrollment->student?->student_code,
                'student_name' => $enrollment->student?->name,
                'completed_lessons' => $completedCount,
                'total_lessons' => $lessonCount,
                'completion_percent' => $lessonCount ? (int) round($completedCount * 100 / $lessonCount) : 0,
            ];
        });

        return response()->json(['data' => $rows]);
    }

    public function updateProgress(Request $request, string $organizationId, string $courseId, string $lessonId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        $canManage = $request->user()->can('manageCourses', $organization);
        abort_unless($canManage || $request->user()->can('viewCourses', $organization), 403);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['started', 'completed'])],
            'student_id' => [$canManage ? 'nullable' : 'prohibited', 'uuid'],
        ]);
        $course = $this->scopedCourses($organization, $request)->whereIn('status', $canManage ? ['active', 'draft'] : ['active'])->whereKey($courseId)->firstOrFail();
        $lesson = $course->lessons()->where('status', 'active')->whereKey($lessonId)->firstOrFail();
        $student = $canManage && ! empty($validated['student_id'])
            ? $organization->students()->where('status', 'active')->whereKey($validated['student_id'])->firstOrFail()
            : $this->linkedStudent($request, $organization);
        abort_unless($student, 404);
        if (($studentIds = $this->scopedTeacherStudentIds($organization, $request)) !== null) {
            abort_unless($studentIds->contains($student->id), 404);
        }
        abort_unless($course->enrollments()->where('student_id', $student->id)->where('status', 'active')->exists(), 404);
        $prerequisiteIds = DB::table('course_lesson_prerequisites')->where('organization_id', $organization->id)
            ->where('course_id', $course->id)->where('lesson_id', $lesson->id)->pluck('prerequisite_lesson_id');
        if ($prerequisiteIds->isNotEmpty()) {
            $completedPrerequisites = LessonProgress::query()->where('organization_id', $organization->id)
                ->where('course_id', $course->id)->where('student_id', $student->id)
                ->where('status', 'completed')->whereIn('lesson_id', $prerequisiteIds)->count();
            abort_unless($completedPrerequisites === $prerequisiteIds->count(), 422, 'Complete the prerequisite lessons first.');
        }
        $progress = LessonProgress::updateOrCreate(
            ['organization_id' => $organization->id, 'lesson_id' => $lesson->id, 'student_id' => $student->id],
            [
                'course_id' => $course->id, 'status' => $validated['status'],
                'completed_at' => $validated['status'] === 'completed' ? now() : null,
                'updated_by' => $request->user()->id,
            ],
        );
        if ($validated['status'] === 'completed') {
            $activeLessonIds = $course->lessons()->where('status', 'active')->pluck('id');
            $totalLessons = $activeLessonIds->count();
            $completedLessons = $totalLessons ? LessonProgress::query()->where('organization_id', $organization->id)
                ->where('course_id', $course->id)->where('student_id', $student->id)->where('status', 'completed')
                ->whereIn('lesson_id', $activeLessonIds)->count() : 0;
            if ($totalLessons > 0 && $completedLessons === $totalLessons) {
                CourseCertificate::firstOrCreate(
                    ['organization_id' => $organization->id, 'course_id' => $course->id, 'student_id' => $student->id],
                    ['certificate_code' => (string) Str::uuid(), 'completed_lessons' => $completedLessons, 'total_lessons' => $totalLessons, 'issued_at' => now()],
                );
            }
        }

        return response()->json(['data' => [
            'lesson_id' => $lesson->id, 'student_id' => $student->id,
            'status' => $progress->status, 'completed_at' => $progress->completed_at?->toIso8601String(),
        ]]);
    }

    public function assignments(Request $request, string $organizationId, string $courseId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        $canManage = $request->user()->can('manageCourses', $organization);
        $isTeacher = app(TeacherClassScope::class)->assignedStudentIds($request->user(), $organization) !== null;
        abort_unless($canManage || $request->user()->can('viewCourses', $organization), 403);
        $course = $this->scopedCourses($organization, $request)->whereIn('status', $canManage ? ['active', 'draft'] : ['active'])->whereKey($courseId)->firstOrFail();
        $student = null;
        if (! $canManage && ! $isTeacher) {
            $student = $this->linkedStudent($request, $organization);
            abort_unless($student && $course->enrollments()->where('student_id', $student->id)->where('status', 'active')->exists(), 404);
        }
        $query = $course->assignments()->with('lesson:id,title', 'attachments');
        if (! $canManage) $query->where('status', 'published');
        if ($canManage) $query->withCount('submissions');
        $assignments = $query->get()->map(function (Assignment $assignment) use ($student): array {
            $data = $this->assignmentData($assignment);
            if ($student) {
                $submission = $assignment->submissions()->with('attachments')->where('student_id', $student->id)->first();
                $data['my_submission'] = $submission ? $this->submissionData($submission) : null;
            }
            return $data;
        });

        return response()->json(['data' => $assignments]);
    }

    public function storeAssignment(Request $request, string $organizationId, string $courseId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageCourses', $organization), 403);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'lesson_id' => ['nullable', 'uuid'],
            'due_at' => ['nullable', 'date'],
            'max_points' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'status' => ['sometimes', Rule::in(['draft', 'published'])],
        ]);
        $course = $this->scopedCourses($organization, $request)->whereIn('status', ['active', 'draft'])->whereKey($courseId)->firstOrFail();
        if (! empty($validated['lesson_id'])) {
            abort_unless($course->lessons()->where('status', 'active')->whereKey($validated['lesson_id'])->exists(), 404);
        }
        $assignment = $course->assignments()->create([
            'organization_id' => $organization->id,
            ...$validated,
            'status' => $validated['status'] ?? 'published',
            'created_by' => $request->user()->id,
        ])->load('lesson:id,title');

        return response()->json(['data' => $this->assignmentData($assignment)], 201);
    }

    public function submitAssignment(Request $request, string $organizationId, string $assignmentId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewCourses', $organization), 403);
        $validated = $request->validate([
            'submission_text' => ['nullable', 'required_without:attachments', 'string', 'max:50000'],
            'attachments' => ['sometimes', 'array', 'min:1', 'max:5'],
            'attachments.*' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,png,jpg,jpeg,txt'],
        ]);
        $assignment = Assignment::query()->where('organization_id', $organization->id)->whereKey($assignmentId)
            ->with('course')->firstOrFail();
        abort_unless($assignment->course?->status === 'active', 404);
        abort_unless($assignment->status === 'published', 404);
        $student = $this->linkedStudent($request, $organization);
        abort_unless($student && $assignment->course->enrollments()->where('student_id', $student->id)->where('status', 'active')->exists(), 404);
        abort_if($assignment->due_at && $assignment->due_at->isPast(), 422, 'The assignment deadline has passed.');
        $submission = DB::transaction(function () use ($organization, $assignment, $student, $validated, $request): AssignmentSubmission {
            Assignment::query()->where('organization_id', $organization->id)->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $submission = AssignmentSubmission::updateOrCreate(
                ['organization_id' => $organization->id, 'assignment_id' => $assignment->id, 'student_id' => $student->id],
                [
                    'course_id' => $assignment->course_id,
                    'submission_text' => $validated['submission_text'] ?? null,
                    'submitted_at' => now(), 'score' => null, 'feedback' => null,
                    'status' => 'submitted', 'graded_by' => null, 'graded_at' => null,
                ],
            );
            $existingCount = $submission->attachments()->count();
            abort_if($existingCount + count($validated['attachments'] ?? []) > 5, 422, 'A submission can contain up to five files.');
            foreach ($validated['attachments'] ?? [] as $file) {
                $this->storeAssignmentAttachment($file, $assignment, $request, $submission);
            }

            return $submission;
        });

        return response()->json(['data' => $this->submissionData($submission->load('attachments'))], 201);
    }

    public function uploadAssignmentResources(Request $request, string $organizationId, string $courseId, string $assignmentId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageCourses', $organization), 403);
        $course = $this->scopedCourses($organization, $request)->whereIn('status', ['active', 'draft'])->whereKey($courseId)->firstOrFail();
        $assignment = $course->assignments()->whereKey($assignmentId)->firstOrFail();
        $validated = $request->validate([
            'attachments' => ['required', 'array', 'min:1', 'max:5'],
            'attachments.*' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,png,jpg,jpeg,txt'],
        ]);
        $attachments = DB::transaction(function () use ($validated, $assignment, $request, $organization): array {
            $lockedAssignment = Assignment::query()->where('organization_id', $organization->id)->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedAssignment->attachments()->whereNull('submission_id')->count() + count($validated['attachments']) > 5, 422, 'An assignment can contain up to five resource files.');
            return array_map(fn (UploadedFile $file): array => $this->attachmentData($this->storeAssignmentAttachment($file, $lockedAssignment, $request)), $validated['attachments']);
        });

        return response()->json(['data' => $attachments], 201);
    }

    public function downloadAssignmentAttachment(Request $request, string $organizationId, string $courseId, string $assignmentId, string $attachmentId)
    {
        $organization = $this->memberOrganization($request, $organizationId);
        $canManage = $request->user()->can('manageCourses', $organization);
        abort_unless($canManage || $request->user()->can('viewCourses', $organization), 403);
        $course = $this->scopedCourses($organization, $request)->whereIn('status', $canManage ? ['active', 'draft'] : ['active'])->whereKey($courseId)->firstOrFail();
        $assignment = $course->assignments()->whereKey($assignmentId)->firstOrFail();
        if (! $canManage) {
            abort_unless($assignment->status === 'published', 404);
            $student = $this->linkedStudent($request, $organization);
            abort_unless($student && $course->enrollments()->where('student_id', $student->id)->where('status', 'active')->exists(), 404);
        } else {
            $student = null;
        }
        $attachment = $assignment->attachments()->whereKey($attachmentId)->firstOrFail();
        if ($attachment->submission_id) {
            $submission = AssignmentSubmission::query()->where('organization_id', $organization->id)->where('assignment_id', $assignment->id)->whereKey($attachment->submission_id)->firstOrFail();
            if ($student) abort_unless($submission->student_id === $student->id, 404);
            if ($canManage && ($studentIds = $this->scopedTeacherStudentIds($organization, $request)) !== null) {
                abort_unless($studentIds->contains($submission->student_id), 404);
            }
        }
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->storage_path), 404);

        return Storage::disk($attachment->disk)->download($attachment->storage_path, $attachment->original_name, [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function assignmentSubmissions(Request $request, string $organizationId, string $courseId, string $assignmentId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageCourses', $organization), 403);
        $course = $this->scopedCourses($organization, $request)->whereKey($courseId)->firstOrFail();
        $assignment = $course->assignments()->whereKey($assignmentId)->firstOrFail();
        $submissionsQuery = $assignment->submissions()->with('student:id,student_code,name', 'attachments')->latest('submitted_at');
        if (($studentIds = $this->scopedTeacherStudentIds($organization, $request)) !== null) {
            $submissionsQuery->whereIn('student_id', $studentIds);
        }
        $submissions = $submissionsQuery->get()
            ->map(fn (AssignmentSubmission $submission): array => [
                ...$this->submissionData($submission),
                'student_name' => $submission->student?->name,
                'student_code' => $submission->student?->student_code,
            ]);

        return response()->json(['data' => $submissions]);
    }

    public function gradeSubmission(Request $request, string $organizationId, string $courseId, string $assignmentId, string $submissionId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageCourses', $organization), 403);
        $course = $this->scopedCourses($organization, $request)->whereKey($courseId)->firstOrFail();
        $assignment = $course->assignments()->whereKey($assignmentId)->firstOrFail();
        $submission = $assignment->submissions()->whereKey($submissionId)->firstOrFail();
        $validated = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:'.$assignment->max_points],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);
        $submission->forceFill([
            'score' => $validated['score'], 'feedback' => $validated['feedback'] ?? null,
            'status' => 'graded', 'graded_by' => $request->user()->id, 'graded_at' => now(),
        ])->save();

        return response()->json(['data' => $this->submissionData($submission->refresh()->load('attachments'))]);
    }

    public function studentMemberCandidates(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $linkedIds = $organization->students()->whereNotNull('user_id')->select('user_id');
        $users = $organization->users()->wherePivot('status', 'active')->wherePivot('role', 'student')
            ->where('users.account_status', 'active')->whereNotIn('users.id', $linkedIds)->orderBy('users.name')
            ->get(['users.id', 'users.name'])->map(fn ($user): array => ['id' => $user->id, 'name' => $user->name]);
        return response()->json(['data' => $users]);
    }

    public function linkStudentMember(Request $request, string $organizationId, string $studentId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $validated = $request->validate(['user_id' => ['present', 'nullable', 'integer']]);
        $student = $organization->students()->whereKey($studentId)->firstOrFail();
        if ($validated['user_id'] !== null) {
            $member = $organization->users()->wherePivot('status', 'active')->wherePivot('role', 'student')
                ->where('users.account_status', 'active')->where('users.id', $validated['user_id'])->first();
            abort_unless($member, 404);
            abort_if($organization->students()->where('user_id', $member->id)->where('id', '!=', $student->id)->exists(), 422, 'This member is already linked to a student profile.');
        }
        $student->forceFill(['user_id' => $validated['user_id']])->save();
        return response()->json(['data' => ['id' => $student->id, 'name' => $student->name, 'user_id' => $student->user_id, 'member_name' => $student->user?->name]]);
    }

    private function memberOrganization(Request $request, string $organizationId): Organization
    {
        return $request->user()->organizations()->wherePivot('status', 'active')
            ->where('organizations.status', 'active')->where('organizations.id', $organizationId)->firstOrFail();
    }

    private function linkedStudent(Request $request, Organization $organization): ?StudentProfile
    {
        return $organization->students()->where('status', 'active')->where('user_id', $request->user()->id)->first();
    }

    private function scopedCourses(Organization $organization, Request $request)
    {
        return app(TeacherCourseScope::class)->courses($organization, $request->user());
    }

    private function scopedTeacherStudentIds(Organization $organization, Request $request)
    {
        return app(TeacherClassScope::class)->assignedStudentIds($request->user(), $organization);
    }

    private function courseData(Course $course): array
    {
        return [
            'id' => $course->id, 'code' => $course->code, 'title' => $course->title,
            'description' => $course->description, 'status' => $course->status,
            'is_public' => (bool) $course->is_public,
            'price_paise' => (int) $course->price_paise,
            'academic_year_id' => $course->academic_year_id, 'academic_year' => $course->academicYear?->name,
            'subject_id' => $course->subject_id, 'subject' => $course->subject?->name,
            'instructors' => $course->relationLoaded('instructors') ? $course->instructors->map(fn ($instructor): array => [
                'id' => $instructor->id, 'name' => $instructor->name,
            ])->values()->all() : [],
            'lessons_count' => $course->lessons_count ?? $course->lessons()->where('status', 'active')->count(),
            'enrollments_count' => $course->enrollments_count ?? $course->enrollments()->where('status', 'active')->count(),
        ];
    }

    private function assignmentData(Assignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'course_id' => $assignment->course_id,
            'lesson_id' => $assignment->lesson_id,
            'lesson_title' => $assignment->lesson?->title,
            'title' => $assignment->title,
            'instructions' => $assignment->instructions,
            'due_at' => $assignment->due_at?->toIso8601String(),
            'max_points' => (float) $assignment->max_points,
            'status' => $assignment->status,
            'submissions_count' => $assignment->submissions_count ?? null,
            'resource_attachments' => $assignment->relationLoaded('attachments')
                ? $assignment->attachments->whereNull('submission_id')->map(fn (AssignmentAttachment $attachment): array => $this->attachmentData($attachment))->values()
                : [],
        ];
    }

    private function submissionData(AssignmentSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'status' => $submission->status,
            'submission_text' => $submission->submission_text,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'score' => $submission->score === null ? null : (float) $submission->score,
            'feedback' => $submission->feedback,
            'graded_at' => $submission->graded_at?->toIso8601String(),
            'attachments' => $submission->relationLoaded('attachments')
                ? $submission->attachments->map(fn (AssignmentAttachment $attachment): array => $this->attachmentData($attachment))->values()
                : [],
        ];
    }

    private function storeAssignmentAttachment(UploadedFile $file, Assignment $assignment, Request $request, ?AssignmentSubmission $submission = null): AssignmentAttachment
    {
        $disk = config('filesystems.assignment_disk', 'local');
        abort_if(config('filesystems.disks.'.$disk.'.visibility') === 'public', 500, 'Assignment attachments require a private filesystem disk.');
        $directory = 'assignments/'.$assignment->organization_id.'/'.$assignment->id;
        if ($submission) $directory .= '/submissions/'.$submission->id;
        $name = (string) Str::uuid().'.'.($file->guessExtension() ?: 'bin');
        $path = $file->storeAs($directory, $name, $disk);
        abort_unless(is_string($path) && $path !== '', 500, 'The attachment could not be stored.');
        $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '_', $originalName) ?: 'attachment';

        try {
            return AssignmentAttachment::create([
                'organization_id' => $assignment->organization_id, 'course_id' => $assignment->course_id,
                'assignment_id' => $assignment->id, 'submission_id' => $submission?->id,
                'uploaded_by' => $request->user()->id, 'disk' => $disk, 'storage_path' => $path,
                'original_name' => mb_substr($originalName, 0, 255),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize() ?: 0,
            ]);
        } catch (\Throwable $cause) {
            Storage::disk($disk)->delete($path);
            throw $cause;
        }
    }

    private function attachmentData(AssignmentAttachment $attachment): array
    {
        return [
            'id' => $attachment->id, 'name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type, 'size_bytes' => (int) $attachment->size_bytes,
        ];
    }
}
