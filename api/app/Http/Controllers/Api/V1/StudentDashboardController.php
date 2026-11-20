<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\CourseEnrollment;
use App\Models\CourseCertificate;
use App\Models\AttendanceRecord;
use App\Models\LessonProgress;
use App\Models\Organization;
use App\Models\ScheduleException;
use App\Models\StudentProfile;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentDashboardController extends Controller
{
    public function show(Request $request, string $organizationId): JsonResponse
    {
        $organization = $request->user()->organizations()->wherePivot('status', 'active')
            ->where('organizations.status', 'active')->where('organizations.id', $organizationId)->firstOrFail();
        abort_unless($request->user()->can('viewCourses', $organization), 403);
        $membership = $organization->users()->where('users.id', $request->user()->id)->firstOrFail();
        abort_unless($membership->pivot->role === 'student', 403);
        $canViewResults = $request->user()->can('viewResults', $organization);
        $canViewAttendance = $request->user()->can('viewAttendance', $organization);
        $student = StudentProfile::query()->where('organization_id', $organization->id)->where('user_id', $request->user()->id)
            ->where('status', 'active')->first();
        if (! $student) return response()->json(['data' => ['student' => null, 'courses' => [], 'assignments' => [], 'study_recommendations' => [], 'assessment_results' => [], 'today_schedule' => [], 'schedule_notice' => null, 'attendance' => ['sessions_recorded' => 0, 'present_or_late' => 0, 'rate_percent' => null, 'recent' => []]]]);

        $enrollments = CourseEnrollment::query()->where('organization_id', $organization->id)->where('student_id', $student->id)->where('status', 'active')->get();
        $courseIds = $enrollments->pluck('course_id');
        $courses = $organization->courses()->where('status', 'active')->whereIn('id', $courseIds)
            ->withCount(['lessons' => fn ($query) => $query->where('status', 'active')])->orderBy('title')->get();
        $completedByCourse = LessonProgress::query()->where('organization_id', $organization->id)->where('student_id', $student->id)
            ->where('status', 'completed')->whereIn('course_id', $courses->pluck('id'))->selectRaw('course_id, COUNT(*) as completed_count')
            ->groupBy('course_id')->pluck('completed_count', 'course_id');
        $certificates = CourseCertificate::query()->where('organization_id', $organization->id)->where('student_id', $student->id)
            ->whereIn('course_id', $courses->pluck('id'))->get()->keyBy('course_id');

        $assignments = Assignment::query()->where('organization_id', $organization->id)->whereIn('course_id', $courses->pluck('id'))
            ->where('status', 'published')->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '>=', now()))
            ->with('course:id,title')->orderByRaw('due_at IS NULL')->orderBy('due_at')->limit(8)->get();
        $submissions = AssignmentSubmission::query()->where('organization_id', $organization->id)->where('student_id', $student->id)
            ->whereIn('assignment_id', $assignments->pluck('id'))->get()->keyBy('assignment_id');
        $assessmentResults = $canViewResults ? AssessmentAttempt::query()->where('organization_id', $organization->id)->where('student_id', $student->id)
            ->whereIn('course_id', $courses->pluck('id'))->whereIn('status', ['submitted', 'expired'])
            ->with(['assessment:id,title,course_id'])->orderByDesc('submitted_at')->orderByDesc('created_at')->limit(8)->get() : collect();
        $studyRecommendations = collect();
        foreach ($assignments->reject(fn (Assignment $assignment): bool => $submissions->has($assignment->id))->take(3) as $assignment) {
            $studyRecommendations->push([
                'id' => 'assignment-'.$assignment->id, 'kind' => 'assignment',
                'title' => 'Prepare: '.$assignment->title, 'course_id' => $assignment->course_id,
                'course_title' => $assignment->course?->title,
                'reason' => $assignment->due_at ? 'Upcoming assignment · due '.$assignment->due_at->timezone($organization->timezone)->format('M j') : 'Upcoming assignment · no due date',
                'duration_minutes' => 30,
            ]);
        }
        foreach ($assessmentResults->filter(function (AssessmentAttempt $attempt): bool {
            return in_array($attempt->review_status, ['not_required', 'reviewed'], true)
                && $attempt->score !== null && (float) $attempt->max_points > 0;
        })->unique('course_id')->filter(fn (AssessmentAttempt $attempt): bool =>
            ((float) $attempt->score / (float) $attempt->max_points) < 0.7
        )->take(2) as $attempt) {
            $course = $courses->firstWhere('id', $attempt->course_id);
            if (! $course) continue;
            $scorePercent = (int) round(((float) $attempt->score / (float) $attempt->max_points) * 100);
            $studyRecommendations->push([
                'id' => 'review-'.$attempt->id, 'kind' => 'review',
                'title' => 'Review concepts in '.$course->title, 'course_id' => $course->id,
                'course_title' => $course->title, 'reason' => 'Review recommended · latest reviewed score '.$scorePercent.'%',
                'duration_minutes' => 20,
            ]);
        }
        foreach ($courses as $course) {
            $remaining = max(0, (int) $course->lessons_count - (int) ($completedByCourse->get($course->id) ?? 0));
            if ($remaining === 0) continue;
            $studyRecommendations->push([
                'id' => 'course-'.$course->id, 'kind' => 'course',
                'title' => 'Continue '.$course->title, 'course_id' => $course->id,
                'course_title' => $course->title, 'reason' => $remaining.' lesson'.($remaining === 1 ? '' : 's').' left to complete',
                'duration_minutes' => 25,
            ]);
        }
        $studyRecommendations = $studyRecommendations->take(5)->values();
        $attendance = $canViewAttendance ? AttendanceRecord::query()->where('organization_id', $organization->id)->where('student_id', $student->id)
            ->selectRaw('COUNT(*) as sessions_recorded, SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as present_or_late', ['present', 'late'])
            ->first() : null;
        $recentAttendance = $canViewAttendance ? AttendanceRecord::query()->where('organization_id', $organization->id)->where('student_id', $student->id)
            ->with('session:id,session_date,class_name')->orderByDesc('created_at')->limit(5)->get() : collect();
        $attendanceTotal = (int) ($attendance->sessions_recorded ?? 0);
        $attendancePresentOrLate = (int) ($attendance->present_or_late ?? 0);

        $activeYearId = $organization->academicYears()->where('status', 'active')->value('id');
        $classScopes = DB::table('enrollments')->where('organization_id', $organization->id)->where('student_id', $student->id)
            ->where('status', 'active')->get(['class_id', 'section_id']);
        $today = Carbon::now($organization->timezone);
        $schedule = $activeYearId ? $organization->timetableEntries()
            ->with(['organizationClass:id,name', 'section:id,name', 'subjectCatalog:id,name', 'teacherProfile:id,name'])
            ->where('academic_year_id', $activeYearId)->where('weekday', $today->dayOfWeekIso)
            ->where(function ($scheduleScope) use ($classScopes): void {
                foreach ($classScopes as $enrollment) {
                    $scheduleScope->orWhere(function ($classQuery) use ($enrollment): void {
                        $classQuery->where('class_id', $enrollment->class_id)
                            ->where(fn ($sectionQuery) => $sectionQuery->whereNull('section_id')
                                ->orWhere('section_id', $enrollment->section_id));
                    });
                }
            })->where('starts_at', '>=', $today->format('H:i:s'))
            ->orderBy('starts_at')->limit(12)->get() : collect();
        $exceptions = $activeYearId ? ScheduleException::query()->where('organization_id', $organization->id)
            ->where('academic_year_id', $activeYearId)->whereDate('exception_date', $today->toDateString())->where('status', 'active')
            ->with(['organizationClass:id,name', 'section:id,name', 'subjectCatalog:id,name', 'teacherProfile:id,name'])->get() : collect();
        $closure = $exceptions->firstWhere('type', 'closure');
        $scheduleNotice = $closure?->title;
        if ($closure) {
            $schedule = collect();
        } else {
            $cancellations = $exceptions->where('type', 'cancel');
            $relevantCancellation = $cancellations->first(fn (ScheduleException $exception): bool =>
                $classScopes->contains(fn ($enrollment) => $enrollment->class_id === $exception->class_id
                    && ($exception->section_id === null || $enrollment->section_id === $exception->section_id)));
            if ($relevantCancellation) $scheduleNotice = 'Schedule update: '.$relevantCancellation->title;
            $schedule = $schedule->reject(function ($entry) use ($cancellations, $classScopes): bool {
                return $cancellations->contains(function (ScheduleException $exception) use ($entry, $classScopes): bool {
                    if ($exception->timetable_entry_id) return $exception->timetable_entry_id === $entry->id;
                    if ($exception->class_id !== $entry->class_id) return false;
                    return $classScopes->contains(fn ($enrollment) => $enrollment->class_id === $exception->class_id
                        && ($exception->section_id === null || $enrollment->section_id === $exception->section_id));
                });
            });
        }
        $scheduleItems = $schedule->map(fn ($entry): array => [
            'id' => $entry->id, 'class_name' => $entry->organizationClass?->name, 'section_name' => $entry->section?->name,
            'subject' => $entry->subjectCatalog?->name ?? $entry->subject,
            'teacher_name' => $entry->teacherProfile?->name, 'starts_at' => substr((string) $entry->starts_at, 0, 5),
            'ends_at' => substr((string) $entry->ends_at, 0, 5), 'room' => $entry->room,
            'is_exception' => false, 'title' => null,
        ]);
        if (! $closure) {
            $extraItems = $exceptions->where('type', 'extra')->filter(function (ScheduleException $exception) use ($classScopes, $today): bool {
                return $exception->starts_at >= $today->format('H:i:s')
                    && $classScopes->contains(fn ($enrollment) => $enrollment->class_id === $exception->class_id
                        && ($exception->section_id === null || $enrollment->section_id === $exception->section_id));
            })->map(fn (ScheduleException $exception): array => [
                'id' => $exception->id, 'class_name' => $exception->organizationClass?->name, 'section_name' => $exception->section?->name,
                'subject' => $exception->subjectCatalog?->name ?? $exception->title,
                'teacher_name' => $exception->teacherProfile?->name, 'starts_at' => substr((string) $exception->starts_at, 0, 5),
                'ends_at' => substr((string) $exception->ends_at, 0, 5), 'room' => $exception->room,
                'is_exception' => true, 'title' => $exception->title,
            ]);
            $scheduleItems = $scheduleItems->concat($extraItems)->sortBy('starts_at')->values();
        }

        return response()->json(['data' => [
            'student' => ['name' => $student->name, 'student_code' => $student->student_code],
            'courses' => $courses->map(fn ($course): array => [
                'id' => $course->id, 'code' => $course->code, 'title' => $course->title,
                'total_lessons' => (int) $course->lessons_count,
                'completed_lessons' => (int) ($completedByCourse->get($course->id) ?? 0),
                'certificate' => $certificates->has($course->id) ? [
                    'id' => $certificates->get($course->id)->id,
                    'code' => $certificates->get($course->id)->certificate_code,
                    'issued_at' => $certificates->get($course->id)->issued_at->toIso8601String(),
                    'completed_lessons' => (int) $certificates->get($course->id)->completed_lessons,
                    'total_lessons' => (int) $certificates->get($course->id)->total_lessons,
                ] : null,
            ]),
            'assignments' => $assignments->map(fn (Assignment $assignment): array => [
                'id' => $assignment->id, 'title' => $assignment->title, 'course_title' => $assignment->course?->title,
                'due_at' => $assignment->due_at?->toIso8601String(), 'max_points' => (float) $assignment->max_points,
                'submission_status' => $submissions->get($assignment->id)?->status,
            ]),
            'study_recommendations' => $studyRecommendations,
            'assessment_results' => $assessmentResults->map(fn (AssessmentAttempt $attempt): array => [
                'id' => $attempt->id, 'title' => $attempt->assessment?->title, 'course_title' => $courses->firstWhere('id', $attempt->course_id)?->title,
                'score' => $attempt->score === null ? null : (float) $attempt->score,
                'review_status' => $attempt->review_status,
                'max_points' => (float) $attempt->max_points, 'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            ]),
            'today_schedule' => $scheduleItems,
            'schedule_notice' => $scheduleNotice,
            'attendance' => [
                'sessions_recorded' => $attendanceTotal,
                'present_or_late' => $attendancePresentOrLate,
                'rate_percent' => $attendanceTotal > 0 ? round(($attendancePresentOrLate / $attendanceTotal) * 100, 2) : null,
                'recent' => $recentAttendance->map(fn (AttendanceRecord $record): array => [
                    'date' => $record->session?->session_date?->format('Y-m-d'),
                    'class_name' => $record->session?->class_name,
                    'status' => $record->status,
                ]),
            ],
        ]]);
    }
}
