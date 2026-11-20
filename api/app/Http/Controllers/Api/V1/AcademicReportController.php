<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\TeacherClassScope;
use App\Services\TeacherCourseScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicReportController extends Controller
{
    public function show(Request $request, string $organizationId, TeacherClassScope $teacherScope, TeacherCourseScope $courseScope): JsonResponse
    {
        $organization = $request->user()->organizations()->wherePivot('status', 'active')->where('organizations.status', 'active')
            ->where('organizations.id', $organizationId)->firstOrFail();
        abort_unless($request->user()->can('viewAcademic', $organization)
            && $request->user()->can('viewAttendance', $organization)
            && $request->user()->can('viewReports', $organization), 403);
        $membership = $organization->users()->where('users.id', $request->user()->id)->firstOrFail();
        $role = $membership->pivot->role;
        abort_unless(in_array($role, ['owner', 'admin', 'educator', 'teacher'], true), 403);
        $filters = $request->validate([
            'class_id' => ['sometimes', 'nullable', 'uuid'],
            'student_id' => ['sometimes', 'nullable', 'uuid'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $scope = 'workspace'; $teacherClassIds = null; $teacherAssignments = null; $teacherCourseIds = null;
        if ($role === 'teacher') {
            $teacherAssignments = $teacherScope->assignmentScopes($request->user(), $organization) ?? collect();
            $teacherClassIds = $teacherAssignments->pluck('class_id')->unique()->values();
            $teacherCourseIds = $courseScope->courses($organization, $request->user())->pluck('id');
            $scope = 'assigned_classes';
        }

        $studentsQuery = $organization->students()->where('status', 'active');
        if ($role === 'teacher') {
            $studentsQuery->whereIn('id', $teacherScope->assignedStudentIds($request->user(), $organization) ?? collect());
        }
        $availableClassQuery = $organization->classes()->where('status', 'active');
        if ($role === 'teacher') $availableClassQuery->whereIn('id', $teacherClassIds);
        $availableClasses = $availableClassQuery->orderBy('name')->get(['id', 'name']);
        if (! empty($filters['class_id'])) {
            abort_unless($organization->classes()->where('status', 'active')->whereKey($filters['class_id'])->exists(), 404);
            if ($role === 'teacher') abort_unless($teacherClassIds->contains($filters['class_id']), 404);
            $classEnrollments = DB::table('enrollments')->where('organization_id', $organization->id)
                ->where('class_id', $filters['class_id'])->where('status', 'active');
            if ($role === 'teacher') {
                $classAssignments = $teacherAssignments->where('class_id', $filters['class_id']);
                $classEnrollments->where(function ($scopeQuery) use ($classAssignments): void {
                    foreach ($classAssignments as $assignment) {
                        $scopeQuery->orWhere(function ($assignmentQuery) use ($assignment): void {
                            $assignmentQuery->where('class_id', $assignment->class_id);
                            if ($assignment->section_id !== null) $assignmentQuery->where('section_id', $assignment->section_id);
                        });
                    }
                });
            }
            $studentsQuery->whereIn('id', $classEnrollments->select('student_id'));
        }
        if (! empty($filters['student_id'])) $studentsQuery->whereKey($filters['student_id']);
        $students = $studentsQuery->orderBy('name')->get(['id', 'student_code', 'name']);
        $studentIds = $students->pluck('id');
        $attendance = DB::table('attendance_records as records')->join('attendance_sessions as sessions', function ($join) use ($organization): void {
            $join->on('records.attendance_session_id', '=', 'sessions.id')->where('sessions.organization_id', '=', $organization->id);
        })->where('records.organization_id', $organization->id)->whereIn('records.student_id', $studentIds)
            ->when($role === 'teacher', function ($query) use ($teacherAssignments, $request): void {
                $query->where(function ($scoped) use ($teacherAssignments, $request): void {
                    foreach ($teacherAssignments as $assignment) {
                        $scoped->orWhere(function ($assignmentQuery) use ($assignment): void {
                            $assignmentQuery->where('sessions.class_id', $assignment->class_id);
                            if ($assignment->section_id !== null) $assignmentQuery->where('sessions.section_id', $assignment->section_id);
                        });
                    }
                    $scoped->orWhere(fn ($legacy) => $legacy->whereNull('sessions.class_id')->where('sessions.recorded_by', $request->user()->id));
                });
            })
            ->when(! empty($filters['class_id']), fn ($query) => $query->where('sessions.class_id', $filters['class_id']))
            ->when(! empty($filters['from']), fn ($query) => $query->whereDate('sessions.session_date', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($query) => $query->whereDate('sessions.session_date', '<=', $filters['to']))
            ->selectRaw("records.student_id, COUNT(*) as total, SUM(CASE WHEN records.status IN ('present','late') THEN 1 ELSE 0 END) as present")
            ->groupBy('records.student_id')->get()->keyBy('student_id');
        $classQuery = DB::table('enrollments')->join('organization_classes', function ($join) use ($organization): void {
            $join->on('organization_classes.id', '=', 'enrollments.class_id')->where('organization_classes.organization_id', '=', $organization->id);
        })->leftJoin('organization_sections', function ($join): void {
            $join->on('organization_sections.id', '=', 'enrollments.section_id')->on('organization_sections.organization_id', '=', 'enrollments.organization_id');
        })->where('enrollments.organization_id', $organization->id)->where('enrollments.status', 'active')->whereIn('enrollments.student_id', $studentIds);
        if ($role === 'teacher') {
            $classQuery->whereIn('enrollments.class_id', $teacherClassIds)->where(function ($scopeQuery) use ($teacherAssignments): void {
                foreach ($teacherAssignments as $assignment) {
                    $scopeQuery->orWhere(function ($assignmentQuery) use ($assignment): void {
                        $assignmentQuery->where('enrollments.class_id', $assignment->class_id);
                        if ($assignment->section_id !== null) $assignmentQuery->where('enrollments.section_id', $assignment->section_id);
                    });
                }
            });
        }
        $classNames = $classQuery->select('enrollments.student_id', 'organization_classes.name', 'organization_sections.name as section_name')->orderBy('organization_classes.name')->get()->groupBy('student_id');
        $courseEnrollmentRows = DB::table('course_enrollments as enrollment')->join('courses', function ($join) use ($organization): void {
            $join->on('courses.id', '=', 'enrollment.course_id')->where('courses.organization_id', '=', $organization->id)->where('courses.status', '=', 'active');
        })->leftJoin('course_lessons as lessons', function ($join): void {
            $join->on('lessons.course_id', '=', 'courses.id')->where('lessons.status', '=', 'active');
        })->where('enrollment.organization_id', $organization->id)->where('enrollment.status', 'active')->whereIn('enrollment.student_id', $studentIds)
            ->when($role === 'teacher', fn ($query) => $query->whereIn('courses.id', $teacherCourseIds))
            ->selectRaw('enrollment.student_id, COUNT(DISTINCT enrollment.id) as courses, COUNT(DISTINCT lessons.id) as total_lessons')
            ->groupBy('enrollment.student_id')->get()->keyBy('student_id');
        $completedLessons = DB::table('lesson_progress as progress')->join('course_enrollments as enrollment', function ($join): void {
            $join->on('enrollment.organization_id', '=', 'progress.organization_id')->on('enrollment.course_id', '=', 'progress.course_id')
                ->on('enrollment.student_id', '=', 'progress.student_id')->where('enrollment.status', '=', 'active');
        })->join('courses', function ($join) use ($organization): void {
            $join->on('courses.id', '=', 'progress.course_id')->where('courses.organization_id', '=', $organization->id)->where('courses.status', '=', 'active');
        })->join('course_lessons as lessons', function ($join) use ($organization): void {
            $join->on('lessons.id', '=', 'progress.lesson_id')->on('lessons.course_id', '=', 'progress.course_id')
                ->where('lessons.organization_id', '=', $organization->id)->where('lessons.status', '=', 'active');
        })->where('progress.organization_id', $organization->id)->where('progress.status', 'completed')
            ->whereIn('progress.student_id', $studentIds)->when($role === 'teacher', fn ($query) => $query->whereIn('progress.course_id', $teacherCourseIds))
            ->selectRaw('progress.student_id, COUNT(DISTINCT progress.id) as completed')->groupBy('progress.student_id')->pluck('completed', 'progress.student_id');
        $assignmentWorkload = DB::table('assignments as assignment')->join('courses', function ($join) use ($organization): void {
            $join->on('courses.id', '=', 'assignment.course_id')->where('courses.organization_id', '=', $organization->id)->where('courses.status', '=', 'active');
        })->join('course_enrollments as enrollment', function ($join) use ($organization): void {
            $join->on('enrollment.organization_id', '=', 'assignment.organization_id')->on('enrollment.course_id', '=', 'assignment.course_id')
                ->where('enrollment.status', '=', 'active')->where('enrollment.organization_id', '=', $organization->id);
        })->where('assignment.organization_id', $organization->id)->where('assignment.status', 'published')->whereIn('enrollment.student_id', $studentIds)
            ->when($role === 'teacher', fn ($query) => $query->whereIn('assignment.course_id', $teacherCourseIds))
            ->selectRaw('enrollment.student_id, COUNT(DISTINCT assignment.id) as assigned')->groupBy('enrollment.student_id')->pluck('assigned', 'enrollment.student_id');
        $assignmentStats = DB::table('assignment_submissions as submission')->join('assignments as assignment', function ($join): void {
            $join->on('assignment.id', '=', 'submission.assignment_id')->on('assignment.organization_id', '=', 'submission.organization_id')
                ->on('assignment.course_id', '=', 'submission.course_id')->where('assignment.status', '=', 'published');
        })->join('courses', function ($join) use ($organization): void {
            $join->on('courses.id', '=', 'submission.course_id')->where('courses.organization_id', '=', $organization->id)->where('courses.status', '=', 'active');
        })->join('course_enrollments as enrollment', function ($join): void {
            $join->on('enrollment.organization_id', '=', 'submission.organization_id')->on('enrollment.course_id', '=', 'submission.course_id')
                ->on('enrollment.student_id', '=', 'submission.student_id')->where('enrollment.status', '=', 'active');
        })->where('submission.organization_id', $organization->id)->whereIn('submission.student_id', $studentIds)
            ->when($role === 'teacher', fn ($query) => $query->whereIn('submission.course_id', $teacherCourseIds))
            ->when(! empty($filters['from']), fn ($query) => $query->whereDate('submission.submitted_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($query) => $query->whereDate('submission.submitted_at', '<=', $filters['to']))
            ->selectRaw("submission.student_id, COUNT(DISTINCT submission.id) as submitted, SUM(CASE WHEN submission.status = 'graded' THEN 1 ELSE 0 END) as graded")
            ->groupBy('submission.student_id')->get()->keyBy('student_id');
        $assessmentQuery = DB::table('assessment_attempts as attempts')->where('attempts.organization_id', $organization->id)->whereIn('attempts.student_id', $studentIds)
            ->when($role === 'teacher', fn ($query) => $query->whereIn('attempts.course_id', $teacherCourseIds))
            ->whereIn('attempts.status', ['submitted', 'expired'])->where('attempts.review_status', '!=', 'pending')->where('attempts.max_points', '>', 0)
            ->when(! empty($filters['from']), fn ($query) => $query->whereDate('attempts.submitted_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($query) => $query->whereDate('attempts.submitted_at', '<=', $filters['to']));
        $assessmentStats = (clone $assessmentQuery)
            ->selectRaw('attempts.student_id, COUNT(*) as completed, AVG(attempts.score * 100.0 / attempts.max_points) as average_percent')
            ->groupBy('attempts.student_id')->get()->keyBy('student_id');
        $assessmentScoreBands = (clone $assessmentQuery)->selectRaw(
            'SUM(CASE WHEN attempts.score * 100.0 / attempts.max_points < 60 THEN 1 ELSE 0 END) as under_60, '
            .'SUM(CASE WHEN attempts.score * 100.0 / attempts.max_points >= 60 AND attempts.score * 100.0 / attempts.max_points < 75 THEN 1 ELSE 0 END) as sixty_to_74, '
            .'SUM(CASE WHEN attempts.score * 100.0 / attempts.max_points >= 75 AND attempts.score * 100.0 / attempts.max_points < 90 THEN 1 ELSE 0 END) as seventyfive_to_89, '
            .'SUM(CASE WHEN attempts.score * 100.0 / attempts.max_points >= 90 THEN 1 ELSE 0 END) as ninety_plus'
        )->first();
        $monthExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', attempts.submitted_at)",
            'pgsql' => "to_char(attempts.submitted_at, 'YYYY-MM')",
            default => "DATE_FORMAT(attempts.submitted_at, '%Y-%m')",
        };
        $assessmentTrend = (clone $assessmentQuery)
            ->selectRaw($monthExpression.' as month, COUNT(*) as attempts, AVG(attempts.score * 100.0 / attempts.max_points) as average_percent')
            ->groupByRaw($monthExpression)->orderBy('month')->get()
            ->map(fn ($row): array => ['month' => $row->month, 'attempts' => (int) $row->attempts, 'average_percent' => round((float) $row->average_percent, 1)])
            ->values();
        $assessmentBreakdown = (clone $assessmentQuery)
            ->join('assessments as assessment', function ($join): void {
                $join->on('assessment.organization_id', '=', 'attempts.organization_id')
                    ->on('assessment.course_id', '=', 'attempts.course_id')
                    ->on('assessment.id', '=', 'attempts.assessment_id');
            })->join('courses as course', function ($join): void {
                $join->on('course.organization_id', '=', 'attempts.organization_id')
                    ->on('course.id', '=', 'attempts.course_id');
            })->select('assessment.id as assessment_id', 'assessment.title as assessment_title', 'course.id as course_id', 'course.title as course_title')
            ->selectRaw('COUNT(*) as attempts, AVG(attempts.score * 100.0 / attempts.max_points) as average_percent')
            ->groupBy('assessment.id', 'assessment.title', 'course.id', 'course.title')
            ->orderBy('course.title')->orderBy('assessment.title')->get()
            ->map(fn ($row): array => [
                'assessment_id' => $row->assessment_id, 'assessment_title' => $row->assessment_title,
                'course_id' => $row->course_id, 'course_title' => $row->course_title,
                'attempts' => (int) $row->attempts, 'average_percent' => round((float) $row->average_percent, 1),
            ])->values();
        $assessmentCourseSummary = (clone $assessmentQuery)
            ->join('courses as course', function ($join): void {
                $join->on('course.organization_id', '=', 'attempts.organization_id')
                    ->on('course.id', '=', 'attempts.course_id');
            })->select('course.id as course_id', 'course.title as course_title')
            ->selectRaw('COUNT(*) as attempts, AVG(attempts.score * 100.0 / attempts.max_points) as average_percent')
            ->groupBy('course.id', 'course.title')->orderBy('course.title')->get()
            ->map(fn ($row): array => [
                'course_id' => $row->course_id, 'course_title' => $row->course_title,
                'attempts' => (int) $row->attempts, 'average_percent' => round((float) $row->average_percent, 1),
            ])->values();

        $rows = $students->map(function ($student) use ($attendance, $classNames, $courseEnrollmentRows, $completedLessons, $assignmentWorkload, $assignmentStats, $assessmentStats): array {
            $att = $attendance->get($student->id); $learning = $courseEnrollmentRows->get($student->id);
            $assignment = $assignmentStats->get($student->id); $assessment = $assessmentStats->get($student->id);
            $completed = (int) ($completedLessons->get($student->id) ?? 0); $totalLessons = (int) ($learning->total_lessons ?? 0);
            return [
                'student_id' => $student->id, 'student_code' => $student->student_code, 'student_name' => $student->name,
                'classes' => ($classNames->get($student->id) ?? collect())->map(fn ($class) => $class->section_name ? $class->name.' · '.$class->section_name : $class->name)->implode(', '),
                'attendance_sessions' => (int) ($att->total ?? 0), 'attendance_percent' => $att ? round($att->present / $att->total * 100, 1) : null,
                'courses_enrolled' => (int) ($learning->courses ?? 0), 'completed_lessons' => $completed, 'total_lessons' => $totalLessons,
                'course_completion_percent' => $totalLessons === 0 ? null : round($completed / $totalLessons * 100, 1),
                'assignments_assigned' => (int) ($assignmentWorkload->get($student->id) ?? 0),
                'assignments_submitted' => (int) ($assignment->submitted ?? 0), 'assignments_graded' => (int) ($assignment->graded ?? 0),
                'assessments_completed' => (int) ($assessment->completed ?? 0), 'assessment_average_percent' => $assessment?->average_percent === null ? null : round((float) $assessment->average_percent, 1),
            ];
        })->values();
        $attendanceRecords = $attendance->sum('total');
        $presentRecords = $attendance->sum('present');
        $totalLessons = $rows->sum('total_lessons'); $completedCount = $rows->sum('completed_lessons');
        $attempts = $assessmentStats->sum('completed');
        $averageValues = $rows->pluck('assessment_average_percent')->filter(fn ($value) => $value !== null);

        return response()->json(['data' => [
            'summary' => [
                'active_students' => $students->count(), 'attendance_sessions' => (int) $attendanceRecords,
                'attendance_percent' => $attendanceRecords === 0 ? null : round($presentRecords / $attendanceRecords * 100, 1),
                'courses_enrolled' => (int) $courseEnrollmentRows->sum('courses'), 'completed_lessons' => (int) $completedCount,
                'total_lessons' => (int) $totalLessons, 'course_completion_percent' => $totalLessons === 0 ? null : round($completedCount / $totalLessons * 100, 1),
                'assignments_assigned' => (int) $assignmentWorkload->sum(),
                'assignments_submitted' => (int) $assignmentStats->sum('submitted'), 'assignments_graded' => (int) $assignmentStats->sum('graded'),
                'assessments_completed' => (int) $attempts, 'assessment_average_percent' => $averageValues->isEmpty() ? null : round((float) $averageValues->avg(), 1),
                'assessment_score_bands' => [
                    'under_60' => (int) ($assessmentScoreBands->under_60 ?? 0),
                    'sixty_to_74' => (int) ($assessmentScoreBands->sixty_to_74 ?? 0),
                    'seventyfive_to_89' => (int) ($assessmentScoreBands->seventyfive_to_89 ?? 0),
                    'ninety_plus' => (int) ($assessmentScoreBands->ninety_plus ?? 0),
                ],
                'assessment_trend' => $assessmentTrend,
                'assessment_breakdown' => $assessmentBreakdown,
                'assessment_course_summary' => $assessmentCourseSummary,
            ], 'scope' => $scope, 'students' => $rows,
            'available_classes' => $availableClasses->map(fn ($class): array => ['id' => $class->id, 'name' => $class->name])->values(),
            'filters' => ['class_id' => $filters['class_id'] ?? null, 'student_id' => $filters['student_id'] ?? null, 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null],
        ]]);
    }
}
