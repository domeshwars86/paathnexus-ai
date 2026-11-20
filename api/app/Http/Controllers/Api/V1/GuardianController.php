<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\CourseEnrollment;
use App\Models\GuardianStudentLink;
use App\Models\Organization;
use App\Models\StudentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GuardianController extends Controller
{
    public function candidates(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);

        $guardians = $organization->users()->wherePivot('status', 'active')->wherePivot('role', 'guardian')
            ->where('users.account_status', 'active')->orderBy('users.name')->get(['users.id', 'users.name', 'users.email']);

        return response()->json(['data' => $guardians->map(fn ($user): array => [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
        ])]);
    }

    public function links(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $links = GuardianStudentLink::query()->where('organization_id', $organization->id)->where('status', 'active')
            ->with(['student:id,student_code,name', 'guardian:id,name,email'])->orderBy('created_at')->get();

        return response()->json(['data' => $links->map(fn (GuardianStudentLink $link): array => [
            'id' => $link->id, 'student_id' => $link->student_id, 'student_name' => $link->student?->name,
            'student_code' => $link->student?->student_code, 'guardian_user_id' => $link->guardian_user_id,
            'guardian_name' => $link->guardian?->name, 'guardian_email' => $link->guardian?->email,
            'relationship' => $link->relationship, 'linked_at' => $link->linked_at?->toIso8601String(),
        ])]);
    }

    public function store(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $validated = $request->validate([
            'student_id' => ['required', 'uuid'],
            'guardian_user_id' => ['required', 'integer'],
            'relationship' => ['required', Rule::in(['parent', 'guardian', 'grandparent', 'caregiver', 'other'])],
        ]);
        $student = $organization->students()->where('status', 'active')->whereKey($validated['student_id'])->firstOrFail();
        $guardian = $organization->users()->whereKey($validated['guardian_user_id'])->wherePivot('status', 'active')
            ->wherePivot('role', 'guardian')->where('users.account_status', 'active')->firstOrFail();

        $link = GuardianStudentLink::where('organization_id', $organization->id)
            ->where('student_id', $student->id)->where('guardian_user_id', $guardian->id)->first();
        if ($link) {
            $link->forceFill(['relationship' => $validated['relationship'], 'status' => 'active', 'linked_by' => $request->user()->id, 'linked_at' => now(), 'ended_at' => null])->save();
            $status = 200;
        } else {
            $link = GuardianStudentLink::create([
                'organization_id' => $organization->id, 'student_id' => $student->id,
                'guardian_user_id' => $guardian->id, 'relationship' => $validated['relationship'],
                'status' => 'active', 'linked_by' => $request->user()->id, 'linked_at' => now(),
            ]);
            $status = 201;
        }
        $link->load(['student:id,student_code,name', 'guardian:id,name,email']);

        return response()->json(['data' => [
            'id' => $link->id, 'student_id' => $link->student_id, 'student_name' => $link->student?->name,
            'student_code' => $link->student?->student_code, 'guardian_user_id' => $link->guardian_user_id,
            'guardian_name' => $link->guardian?->name, 'guardian_email' => $link->guardian?->email,
            'relationship' => $link->relationship, 'linked_at' => $link->linked_at?->toIso8601String(),
        ]], $status);
    }

    public function end(Request $request, string $organizationId, string $linkId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $link = GuardianStudentLink::query()->where('organization_id', $organization->id)->where('status', 'active')->whereKey($linkId)->firstOrFail();
        $link->forceFill(['status' => 'ended', 'ended_at' => now()])->save();
        return response()->json(['data' => ['id' => $link->id, 'status' => $link->status]]);
    }

    public function children(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('viewStudents', $organization), 403);
        $canViewAttendance = $request->user()->can('viewAttendance', $organization);
        $canViewCourses = $request->user()->can('viewCourses', $organization);
        $canViewResults = $request->user()->can('viewResults', $organization);
        $links = GuardianStudentLink::query()->where('organization_id', $organization->id)
            ->where('guardian_user_id', $request->user()->id)->where('status', 'active')->with('student')->get();

        $children = $links->map(function (GuardianStudentLink $link) use ($organization, $canViewAttendance, $canViewCourses, $canViewResults): array {
            $student = $link->student;
            abort_unless($student && $student->status === 'active', 404);
            $attendance = null;
            if ($canViewAttendance) {
                $attendanceQuery = AttendanceRecord::query()->where('organization_id', $organization->id)->where('student_id', $student->id);
                $attendanceStats = (clone $attendanceQuery)->selectRaw("count(*) as sessions_recorded, sum(case when status in ('present', 'late') then 1 else 0 end) as present")->first();
                $recentAttendance = (clone $attendanceQuery)->with('session')->orderByDesc('created_at')->limit(10)->get();
                $attendance = [
                    'sessions_recorded' => (int) $attendanceStats->sessions_recorded, 'present' => (int) $attendanceStats->present,
                    'rate_percent' => (int) $attendanceStats->sessions_recorded === 0 ? null : round((int) $attendanceStats->present / (int) $attendanceStats->sessions_recorded * 100, 1),
                    'recent' => $recentAttendance->map(fn (AttendanceRecord $record): array => [
                        'date' => $record->session?->session_date?->format('Y-m-d'),
                        'class_name' => $record->session?->class_name, 'status' => $record->status,
                    ])->values(),
                ];
            }
            $enrollments = $canViewCourses ? CourseEnrollment::query()->where('organization_id', $organization->id)->where('student_id', $student->id)
                ->where('status', 'active')->whereHas('course', fn ($query) => $query->where('status', 'active'))
                ->with(['course' => fn ($query) => $query->where('status', 'active')->with(['lessons' => fn ($lessons) => $lessons->where('status', 'active')])])->get() : collect();
            $totalLessons = $enrollments->sum(fn (CourseEnrollment $enrollment): int => $enrollment->course?->lessons->count() ?? 0);
            $completedLessons = $canViewCourses ? DB::table('lesson_progress as progress')->join('course_lessons as lessons', function ($join) use ($organization): void {
                $join->on('lessons.id', '=', 'progress.lesson_id')->on('lessons.course_id', '=', 'progress.course_id')
                    ->where('lessons.organization_id', '=', $organization->id)->where('lessons.status', '=', 'active');
            })->where('progress.organization_id', $organization->id)->where('progress.student_id', $student->id)
                ->where('progress.status', 'completed')->whereIn('progress.course_id', $enrollments->pluck('course_id'))
                ->distinct()->count('progress.id') : 0;
            $resultQuery = $canViewResults ? AssessmentAttempt::query()->where('organization_id', $organization->id)->where('student_id', $student->id)
                ->whereIn('status', ['submitted', 'expired'])->where('review_status', '!=', 'pending')->whereNotNull('score')->where('max_points', '>', 0) : null;
            $resultStats = $canViewResults ? (clone $resultQuery)->selectRaw('count(*) as assessments_completed, avg(score * 100.0 / max_points) as average_percent')->first() : null;
            $recentResults = $canViewResults ? (clone $resultQuery)->with('assessment:id,title')->orderByDesc('submitted_at')->limit(10)->get() : collect();
            $upcomingExams = collect();
            if ($canViewResults && $canViewCourses && $enrollments->isNotEmpty()) {
                $upcomingExams = Assessment::query()->where('organization_id', $organization->id)
                    ->whereIn('course_id', $enrollments->pluck('course_id'))->where('status', 'published')
                    ->whereNotNull('opens_at')->where('opens_at', '>', now())
                    ->where(fn ($query) => $query->whereNull('closes_at')->orWhere('closes_at', '>', now()))
                    ->with('course:id,title')->orderBy('opens_at')->limit(10)->get();
            }
            $homework = collect();
            if ($canViewCourses && $enrollments->isNotEmpty()) {
                $publishedAssignments = Assignment::query()->where('organization_id', $organization->id)
                    ->whereIn('course_id', $enrollments->pluck('course_id'))->where('status', 'published')
                    ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '>=', now()))
                    ->with('course:id,title')->orderByRaw('due_at IS NULL')->orderBy('due_at')->limit(8)->get();
                $submissions = AssignmentSubmission::query()->where('organization_id', $organization->id)
                    ->where('student_id', $student->id)->whereIn('assignment_id', $publishedAssignments->pluck('id'))
                    ->get()->keyBy('assignment_id');
                $homework = $publishedAssignments->map(fn (Assignment $assignment): array => [
                    'id' => $assignment->id, 'title' => $assignment->title,
                    'course_title' => $assignment->course?->title,
                    'due_at' => $assignment->due_at?->toIso8601String(),
                    'submission_status' => $submissions->get($assignment->id)?->status,
                ])->values();
            }

            return [
                'student_id' => $student->id, 'student_name' => $student->name,
                'student_code' => $student->student_code, 'relationship' => $link->relationship,
                ...($attendance !== null ? ['attendance' => $attendance] : []),
                ...($canViewCourses ? ['learning' => [
                    'courses_enrolled' => $enrollments->count(), 'completed_lessons' => $completedLessons,
                    'total_lessons' => $totalLessons,
                    'completion_percent' => $totalLessons === 0 ? 0 : round($completedLessons / $totalLessons * 100, 1),
                    'assignments' => $homework,
                ]] : []),
                ...($canViewResults ? ['results' => [
                    'assessments_completed' => (int) $resultStats->assessments_completed,
                    'average_percent' => $resultStats->average_percent === null ? null : round((float) $resultStats->average_percent, 1),
                    'recent' => $recentResults->map(fn (AssessmentAttempt $attempt): array => [
                        'title' => $attempt->assessment?->title, 'score' => (float) $attempt->score,
                        'max_points' => (float) $attempt->max_points, 'completed_at' => $attempt->submitted_at?->toIso8601String(),
                    ])->values(),
                    'upcoming_exams' => $upcomingExams->map(fn (Assessment $assessment): array => [
                        'title' => $assessment->title, 'course_title' => $assessment->course?->title,
                        'opens_at' => $assessment->opens_at?->toIso8601String(), 'closes_at' => $assessment->closes_at?->toIso8601String(),
                    ])->values(),
                ]] : []),
            ];
        })->values();

        return response()->json(['data' => $children]);
    }

    private function organization(Request $request, string $organizationId): Organization
    {
        return $request->user()->organizations()->wherePivot('status', 'active')->where('organizations.status', 'active')
            ->where('organizations.id', $organizationId)->firstOrFail();
    }
}
