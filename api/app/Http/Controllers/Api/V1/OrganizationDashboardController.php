<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\ScheduleException;
use App\Services\TeacherClassScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OrganizationDashboardController extends Controller
{
    public function show(Request $request, string $organizationId, TeacherClassScope $teacherScope): JsonResponse
    {
        $organization = $request->user()->organizations()
            ->wherePivot('status', 'active')
            ->where('organizations.status', 'active')
            ->where('organizations.id', $organizationId)
            ->firstOrFail();

        $canViewAcademic = $request->user()->can('viewAcademic', $organization);
        $canViewAttendance = $request->user()->can('viewAttendance', $organization)
            && $request->user()->can('viewAcademic', $organization);
        $canViewTasks = $request->user()->can('viewTasks', $organization);
        $assignmentScopes = $teacherScope->assignmentScopes($request->user(), $organization);
        $isTeacher = $assignmentScopes !== null;
        $teacherClassIds = $isTeacher ? $assignmentScopes->pluck('class_id')->unique()->values() : null;
        $wholeClassIds = $isTeacher ? $assignmentScopes->whereNull('section_id')->pluck('class_id')->unique()->values() : null;
        $sectionIds = $isTeacher ? $assignmentScopes->whereNotNull('section_id')->pluck('section_id')->unique()->values() : null;

        $scopedStudentIds = $isTeacher ? ($teacherScope->assignedStudentIds($request->user(), $organization) ?? collect()) : collect();
        $studentsQuery = $organization->students()->where('status', 'active');
        if ($isTeacher) $studentsQuery->whereIn('id', $scopedStudentIds);
        $studentsCount = $canViewAcademic ? $studentsQuery->count() : null;
        $classQuery = $organization->classes()->where('status', 'active');
        if ($isTeacher) $classQuery->whereIn('id', $teacherClassIds);
        $classes = $canViewAcademic
            ? $classQuery->withCount(['students' => function ($students) use ($isTeacher, $scopedStudentIds, $assignmentScopes): void {
                $students->where('student_profiles.status', 'active')->where('enrollments.status', 'active');
                if ($isTeacher) {
                    $students->whereIn('student_profiles.id', $scopedStudentIds)->where(function ($scopeQuery) use ($assignmentScopes): void {
                        foreach ($assignmentScopes as $assignment) {
                            $scopeQuery->orWhere(function ($assignmentQuery) use ($assignment): void {
                                $assignmentQuery->where('enrollments.class_id', $assignment->class_id);
                                if ($assignment->section_id !== null) $assignmentQuery->where('enrollments.section_id', $assignment->section_id);
                            });
                        }
                    });
                }
            }])->orderBy('name')->limit(6)->get()
                ->map(fn ($class): array => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'grade' => $class->grade,
                    'section' => $class->section,
                    'student_count' => $class->students_count,
                ])->values()
            : collect();

        $unassignedStudentsCount = null;
        if ($canViewAcademic && ! $isTeacher) {
            $enrolledStudentIds = $organization->classes()
                ->join('enrollments', 'organization_classes.id', '=', 'enrollments.class_id')
                ->where('enrollments.organization_id', $organization->id)
                ->where('enrollments.status', 'active')
                ->select('enrollments.student_id');
            $unassignedStudentsCount = $organization->students()->where('status', 'active')
                ->whereNotIn('id', $enrolledStudentIds)->count();
        }

        $attendanceQuery = $organization->attendanceSessions();
        if ($isTeacher) {
            $attendanceQuery->where(fn ($scope) => $scope->whereIn('class_id', $wholeClassIds)->orWhereIn('section_id', $sectionIds)
                ->orWhere(fn ($legacy) => $legacy->whereNull('class_id')->where('recorded_by', $request->user()->id)));
        }
        $latestAttendance = $canViewAttendance
            ? (clone $attendanceQuery)->orderByDesc('session_date')->orderByDesc('updated_at')->first()
            : null;
        $attendanceSeries = $canViewAttendance
            ? (clone $attendanceQuery)
                ->selectRaw('session_date, AVG(attendance_rate) as attendance_rate')
                ->groupBy('session_date')
                ->orderByDesc('session_date')
                ->limit(5)
                ->get()
                ->sortBy('session_date')
                ->values()
                ->map(fn ($session): array => [
                    'date' => $session->session_date,
                    'rate' => round((float) $session->attendance_rate, 1),
                ])
            : collect();
        $activeYearId = $canViewAcademic
            ? $organization->academicYears()->where('status', 'active')->value('id')
            : null;
        $todaySchedule = collect();
        $scheduleNotice = null;
        $scheduleDate = Carbon::now($organization->timezone);
        $scheduleWeekday = $scheduleDate->dayOfWeekIso;
        if ($activeYearId) {
            $scheduleQuery = $organization->timetableEntries()
                ->with(['organizationClass:id,name', 'section:id,name', 'subjectCatalog:id,name', 'teacherProfile:id,name'])
                ->where('academic_year_id', $activeYearId)
                ->where('weekday', $scheduleWeekday);
            if ($isTeacher) {
                if ($assignmentScopes->isEmpty()) $scheduleQuery->whereRaw('1 = 0');
                else $scheduleQuery->where(function ($scopeQuery) use ($assignmentScopes): void {
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
            $exceptionQuery = ScheduleException::query()->where('organization_id', $organization->id)
                ->where('academic_year_id', $activeYearId)->whereDate('exception_date', $scheduleDate->toDateString())
                ->where('status', 'active')->with(['organizationClass:id,name', 'section:id,name', 'subjectCatalog:id,name', 'teacherProfile:id,name']);
            if ($isTeacher) {
                if ($assignmentScopes->isEmpty()) $exceptionQuery->where('type', 'closure');
                else $exceptionQuery->where(function ($scopeQuery) use ($assignmentScopes): void {
                    $scopeQuery->where('type', 'closure');
                    foreach ($assignmentScopes as $assignment) {
                        $scopeQuery->orWhere(function ($assignmentQuery) use ($assignment): void {
                            $assignmentQuery->where('class_id', $assignment->class_id);
                            if ($assignment->section_id !== null) {
                                $assignmentQuery->where(fn ($sectionQuery) => $sectionQuery->whereNull('section_id')->orWhere('section_id', $assignment->section_id));
                            }
                        });
                    }
                });
            }
            $exceptions = $exceptionQuery->get();
            $closure = $exceptions->firstWhere('type', 'closure');
            $scheduleNotice = $closure?->title;
            if (! $closure && ($cancellation = $exceptions->firstWhere('type', 'cancel'))) {
                $scheduleNotice = 'Schedule update: '.$cancellation->title;
            }
            $entries = $closure ? collect() : $scheduleQuery->orderBy('starts_at')->get()->reject(function ($entry) use ($exceptions): bool {
                return $exceptions->where('type', 'cancel')->contains(fn (ScheduleException $exception): bool =>
                    $exception->timetable_entry_id
                        ? $exception->timetable_entry_id === $entry->id
                        : ($exception->class_id === $entry->class_id && ($exception->section_id === null || $exception->section_id === $entry->section_id)));
            });
            $todaySchedule = $entries->map(fn ($entry): array => [
                    'id' => $entry->id,
                    'subject' => $entry->subjectCatalog?->name ?? $entry->subject,
                    'teacher_name' => $entry->teacherProfile?->name,
                    'class_name' => $entry->organizationClass?->name,
                    'section_name' => $entry->section?->name,
                    'starts_at' => substr((string) $entry->starts_at, 0, 5),
                    'ends_at' => substr((string) $entry->ends_at, 0, 5),
                    'room' => $entry->room,
                    'is_exception' => false,
                    'title' => null,
                ]);
            if (! $closure) {
                $extraLessons = $exceptions->where('type', 'extra')->map(fn (ScheduleException $exception): array => [
                    'id' => $exception->id,
                    'subject' => $exception->subjectCatalog?->name ?? $exception->title,
                    'teacher_name' => $exception->teacherProfile?->name,
                    'class_name' => $exception->organizationClass?->name,
                    'section_name' => $exception->section?->name,
                    'starts_at' => substr((string) $exception->starts_at, 0, 5),
                    'ends_at' => substr((string) $exception->ends_at, 0, 5),
                    'room' => $exception->room,
                    'is_exception' => true,
                    'title' => $exception->title,
                ]);
                $todaySchedule = $todaySchedule->concat($extraLessons)->sortBy('starts_at')->take(6)->values();
            }
        }

        $activeEnrollmentsCount = null;
        if ($canViewAcademic) {
            $activeEnrollmentsQuery = DB::table('enrollments')->where('organization_id', $organization->id)->where('status', 'active');
            if ($isTeacher) {
                $activeEnrollmentsQuery->whereIn('student_id', $scopedStudentIds)->where(function ($scopeQuery) use ($assignmentScopes): void {
                    foreach ($assignmentScopes as $assignment) {
                        $scopeQuery->orWhere(function ($assignmentQuery) use ($assignment): void {
                            $assignmentQuery->where('class_id', $assignment->class_id);
                            if ($assignment->section_id !== null) $assignmentQuery->where('section_id', $assignment->section_id);
                        });
                    }
                });
            }
            $activeEnrollmentsCount = $activeEnrollmentsQuery->count();
        }

        return response()->json(['data' => [
            'active_students_count' => $studentsCount,
            'active_classes_count' => $canViewAcademic ? ($isTeacher ? $teacherClassIds->count() : $organization->classes()->where('status', 'active')->count()) : null,
            'active_enrollments_count' => $activeEnrollmentsCount,
            'unassigned_students_count' => $unassignedStudentsCount,
            'pending_tasks_count' => $canViewTasks ? $organization->tasks()->where('status', 'pending')->whereNull('archived_at')->count() : null,
            'latest_attendance_rate' => $latestAttendance ? (float) $latestAttendance->attendance_rate : null,
            'latest_attendance_class' => $latestAttendance?->class_name,
            'latest_attendance_date' => $latestAttendance?->session_date->toDateString(),
            'attendance_series' => $attendanceSeries,
            'classes' => $classes,
            'today_schedule' => $todaySchedule,
            'schedule_notice' => $scheduleNotice,
            'schedule_weekday' => $scheduleWeekday,
            'active_academic_year_id' => $activeYearId,
        ]]);
    }
}
