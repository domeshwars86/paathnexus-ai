<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AttendanceSession;
use App\Models\Organization;
use App\Services\AttendanceSessionScope;
use App\Services\TeacherClassScope;
use App\Services\TeacherCourseScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkspaceSearchController extends Controller
{
    public function search(Request $request, string $organizationId, TeacherClassScope $teacherClassScope, TeacherCourseScope $teacherCourseScope, AttendanceSessionScope $attendanceScope): JsonResponse
    {
        $organization = $request->user()->organizations()->wherePivot('status', 'active')->where('organizations.status', 'active')
            ->where('organizations.id', $organizationId)->firstOrFail();
        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:80']]);
        $query = trim($validated['q']);
        if (mb_strlen($query) < 2) {
            throw ValidationException::withMessages(['q' => 'Enter at least two characters to search.']);
        }
        $term = '%'.mb_strtolower($query).'%';
        $user = $request->user();
        $membership = $organization->users()->where('users.id', $user->id)->firstOrFail();
        $role = $membership->pivot->role;
        $results = [];

        $studentIds = match ($role) {
            'owner', 'admin', 'educator' => null,
            'teacher' => $teacherClassScope->assignedStudentIds($user, $organization) ?? collect(),
            'student' => $organization->students()->where('user_id', $user->id)->where('status', 'active')->pluck('id'),
            'guardian' => DB::table('guardian_student_links')->where('organization_id', $organization->id)
                ->where('guardian_user_id', $user->id)->where('status', 'active')->pluck('student_id'),
            default => collect(),
        };

        if ($user->can('viewAcademic', $organization)) {
            $classes = $organization->classes()->where('status', 'active');
            if ($role === 'teacher') {
                $classes->whereIn('id', $teacherClassScope->assignedClassIds($user, $organization) ?? collect());
            }
            foreach ($classes->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(grade) LIKE ?', [$term])->orWhereRaw('LOWER(section) LIKE ?', [$term]))
                ->orderBy('name')->limit(8)->get(['id', 'name', 'grade', 'section']) as $class) {
                $results[] = ['id' => $class->id, 'type' => 'class', 'title' => $class->name, 'subtitle' => trim(($class->grade ? 'Grade '.$class->grade : '').($class->section ? ' · '.$class->section : ''))];
            }
        } elseif ($studentIds !== null && $studentIds->isNotEmpty() && $user->can('viewCourses', $organization)) {
            $classes = DB::table('enrollments')->join('organization_classes', function ($join) use ($organization): void {
                $join->on('organization_classes.id', '=', 'enrollments.class_id')->where('organization_classes.organization_id', '=', $organization->id);
            })->where('enrollments.organization_id', $organization->id)->where('enrollments.status', 'active')
                ->whereIn('enrollments.student_id', $studentIds)->where('organization_classes.status', 'active')
                ->whereRaw('LOWER(organization_classes.name) LIKE ?', [$term])->select('organization_classes.id', 'organization_classes.name')->distinct()->orderBy('organization_classes.name')->limit(8)->get();
            foreach ($classes as $class) $results[] = ['id' => $class->id, 'type' => 'class', 'title' => $class->name, 'subtitle' => 'Enrolled class'];
        }

        if ($user->can('viewAcademic', $organization) || $studentIds !== null) {
            $students = $organization->students()->where('status', 'active');
            if ($studentIds !== null) $students->whereIn('id', $studentIds);
            foreach ($students->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(student_code) LIKE ?', [$term]))
                ->orderBy('name')->limit(8)->get(['id', 'name', 'student_code']) as $student) {
                $results[] = ['id' => $student->id, 'type' => 'student', 'title' => $student->name, 'subtitle' => $student->student_code];
            }
        }

        $canManageCourses = $user->can('manageCourses', $organization);
        $canViewLearnerCourses = in_array($role, ['student', 'guardian'], true)
            && $user->can('viewCourses', $organization);
        if ($user->can('viewCourses', $organization) || $canManageCourses || $canViewLearnerCourses) {
            $courses = $role === 'teacher' ? $teacherCourseScope->courses($organization, $user) : $organization->courses();
            $courses->whereIn('status', $canManageCourses ? ['active', 'draft'] : ['active']);
            if ($role === 'student' && $studentIds !== null) {
                $courses->whereHas('enrollments', fn ($enrollments) => $enrollments->whereIn('student_id', $studentIds)->where('status', 'active'));
            } elseif ($role === 'guardian' && $studentIds !== null) {
                $courses->whereHas('enrollments', fn ($enrollments) => $enrollments->whereIn('student_id', $studentIds)->where('status', 'active'));
            }
            $matchingLessons = fn ($lessons) => $lessons->where('organization_id', $organization->id)->where('status', 'active')
                ->where(fn ($q) => $q->whereRaw('LOWER(title) LIKE ?', [$term])->orWhereRaw('LOWER(content) LIKE ?', [$term]));
            foreach ($courses->where(fn ($q) => $q->whereRaw('LOWER(title) LIKE ?', [$term])->orWhereRaw('LOWER(code) LIKE ?', [$term])
                ->orWhereHas('lessons', $matchingLessons))
                ->with(['lessons' => fn ($lessons) => $matchingLessons($lessons)->orderBy('position')->limit(1)])
                ->orderBy('title')->limit(8)->get(['id', 'title', 'code']) as $course) {
                $matchingLesson = $course->lessons->first();
                $results[] = [
                    'id' => $course->id, 'type' => 'course', 'title' => $course->title,
                    'subtitle' => $matchingLesson ? 'Lesson: '.$matchingLesson->title.' · '.$course->code : $course->code,
                ];
            }
        }

        if ($user->can('viewTasks', $organization)) {
            foreach ($organization->tasks()->whereNull('archived_at')->whereRaw('LOWER(title) LIKE ?', [$term])->orderByDesc('created_at')->limit(8)->get(['id', 'title', 'status']) as $task) {
                $results[] = ['id' => $task->id, 'type' => 'task', 'title' => $task->title, 'subtitle' => ucfirst($task->status)];
            }
        }

        $canViewInvoices = $user->can('viewFeeInvoices', $organization)
            && in_array($role, ['owner', 'admin', 'educator', 'student', 'guardian'], true);
        if ($canViewInvoices) {
            $invoices = DB::table('fee_invoices as invoices')->join('student_profiles as student', function ($join) use ($organization): void {
                $join->on('student.organization_id', '=', 'invoices.organization_id')
                    ->on('student.id', '=', 'invoices.student_id');
            })->where('invoices.organization_id', $organization->id);
            if ($role === 'student') {
                $invoices->whereIn('invoices.student_id', $organization->students()->where('user_id', $user->id)->select('id'));
            } elseif ($role === 'guardian') {
                $invoices->whereIn('invoices.student_id', DB::table('guardian_student_links')->where('organization_id', $organization->id)
                    ->where('guardian_user_id', $user->id)->where('status', 'active')->select('student_id'));
            }
            foreach ($invoices->where(fn ($q) => $q->whereRaw('LOWER(invoices.invoice_number) LIKE ?', [$term])
                ->orWhereRaw('LOWER(invoices.title) LIKE ?', [$term])
                ->orWhereRaw('LOWER(student.name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(student.student_code) LIKE ?', [$term]))
                ->orderByDesc('invoices.created_at')->limit(8)
                ->get(['invoices.id', 'invoices.invoice_number', 'invoices.title', 'student.student_code']) as $invoice) {
                $results[] = [
                    'id' => $invoice->id, 'type' => 'invoice', 'title' => $invoice->invoice_number,
                    'subtitle' => $invoice->title.' · '.$invoice->student_code,
                ];
            }
        }

        if ($user->can('view', $organization)) {
            $canManageAnnouncements = $user->can('manageAcademic', $organization);
            $announcements = Announcement::query()->where('organization_id', $organization->id);
            if ($canManageAnnouncements) {
                $announcements->whereIn('status', ['published', 'scheduled', 'withdrawn']);
            } else {
                $audience = match ($role) {
                    'teacher' => 'teachers', 'student' => 'students', 'guardian' => 'guardians', default => 'none',
                };
                $announcements->where('status', 'published')
                    ->where(fn ($visible) => $visible->where('audience', 'all_members')->orWhere('audience', $audience));
            }
            foreach ($announcements->where(fn ($matches) => $matches
                ->whereRaw('LOWER(title) LIKE ?', [$term])->orWhereRaw('LOWER(body) LIKE ?', [$term]))
                ->orderByRaw('COALESCE(published_at, scheduled_at) DESC')->limit(8)
                ->get(['id', 'title', 'audience', 'status']) as $announcement) {
                $results[] = [
                    'id' => $announcement->id, 'type' => 'announcement', 'title' => $announcement->title,
                    'subtitle' => ucfirst(str_replace('_', ' ', $announcement->audience)).' · '.ucfirst($announcement->status),
                ];
            }
        }

        if ($user->can('viewAttendance', $organization) && $user->can('viewAcademic', $organization)) {
            $visibleSessions = $attendanceScope->forMember($organization, $user, $teacherClassScope, $teacherCourseScope)
                ->orderByDesc('session_date')->orderByDesc('updated_at')->limit(30)->select('attendance_sessions.id');
            $dateTerm = preg_match('/^\d{4}-\d{2}-\d{2}$/', $query) === 1
                && checkdate((int) substr($query, 5, 2), (int) substr($query, 8, 2), (int) substr($query, 0, 4))
                ? $query
                : null;
            $attendance = $attendanceScope->forMember($organization, $user, $teacherClassScope, $teacherCourseScope)
                ->whereIn('attendance_sessions.id', $visibleSessions)
                ->where(fn ($matches) => $matches->whereRaw('LOWER(class_name) LIKE ?', [$term])
                    ->when($dateTerm !== null, fn ($dateMatches) => $dateMatches->orWhereDate('session_date', $dateTerm)));
            foreach ($attendance->orderByDesc('session_date')->orderByDesc('updated_at')->limit(8)
                ->get(['attendance_sessions.id', 'class_name', 'session_date', 'attendance_rate']) as $session) {
                $results[] = [
                    'id' => $session->id, 'type' => 'attendance', 'title' => $session->class_name,
                    'subtitle' => $session->session_date->toDateString().' · '.rtrim(rtrim(number_format((float) $session->attendance_rate, 1), '0'), '.').'% present',
                ];
            }
        }

        return response()->json(['data' => array_slice($results, 0, 20)]);
    }
}
