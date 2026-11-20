<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\LiveSession;
use App\Models\Organization;
use App\Services\TeacherClassScope;
use App\Services\TeacherCourseScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LiveSessionController extends Controller
{
    public function index(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        $role = $this->role($request, $organization);
        abort_unless($request->user()->can('viewLiveSessions', $organization), 403);
        $query = LiveSession::query()->where('organization_id', $organization->id)
            ->with(['classroom:id,name', 'section:id,name', 'course:id,title'])
            ->orderBy('starts_at')->limit(200);

        if ($role === 'teacher') {
            $this->teacherScope($query, $request, $organization);
        } elseif ($role === 'student') {
            $student = $organization->students()->where('status', 'active')->where('user_id', $request->user()->id)->first();
            if (! $student) return response()->json(['data' => []]);
            $classEnrollments = DB::table('enrollments')->where('organization_id', $organization->id)
                ->where('student_id', $student->id)->where('status', 'active')->get(['class_id', 'section_id']);
            $courseIds = DB::table('course_enrollments')->where('organization_id', $organization->id)
                ->where('student_id', $student->id)->where('status', 'active')->pluck('course_id');
            $courseIds = $organization->courses()->where('status', 'active')->whereIn('id', $courseIds)->pluck('id');
            if ($classEnrollments->isEmpty() && $courseIds->isEmpty()) return response()->json(['data' => []]);
            $query->where(function ($audience) use ($classEnrollments, $courseIds): void {
                foreach ($classEnrollments as $enrollment) {
                    $audience->orWhere(fn ($class) => $class->where('class_id', $enrollment->class_id)
                        ->where(fn ($section) => $section->whereNull('section_id')->orWhere('section_id', $enrollment->section_id)));
                }
                if ($courseIds->isNotEmpty()) $audience->orWhereIn('course_id', $courseIds);
            });
        } else {
            abort_unless(in_array($role, ['owner', 'admin', 'educator'], true), 403);
        }

        return response()->json(['data' => $query->get()->map(fn (LiveSession $session): array => $this->data($session, $role))]);
    }

    public function store(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        $role = $this->role($request, $organization);
        abort_unless(in_array($role, ['owner', 'admin', 'educator', 'teacher'], true)
            && $request->user()->can('manageLiveSessions', $organization), 403);
        $validated = $request->validate([
            'class_id' => ['nullable', 'uuid', 'required_without:course_id'],
            'section_id' => ['nullable', 'uuid'],
            'course_id' => ['nullable', 'uuid', 'required_without:class_id'],
            'title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:3000'],
            'starts_at' => ['required', 'date', 'after:now'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'meeting_url' => ['required', 'url', 'max:2048', $this->httpsRule()],
        ]);
        abort_if(! empty($validated['class_id']) === ! empty($validated['course_id']), 422, 'Choose one class audience or one course audience.');
        $class = ! empty($validated['class_id']) ? $organization->classes()->where('status', 'active')->whereKey($validated['class_id'])->firstOrFail() : null;
        if ($class && ! empty($validated['section_id'])) $class->sections()->where('status', 'active')->whereKey($validated['section_id'])->firstOrFail();
        $course = ! empty($validated['course_id']) ? $organization->courses()->where('status', 'active')->whereKey($validated['course_id'])->firstOrFail() : null;
        if ($role === 'teacher') $this->assertTeacherCanManageAudience($request, $organization, $class?->id, $validated['section_id'] ?? null, $course?->id);

        $session = LiveSession::create([
            ...$validated, 'organization_id' => $organization->id, 'created_by' => $request->user()->id,
            'provider' => $this->providerFromUrl($validated['meeting_url']), 'status' => 'scheduled',
        ])->load(['classroom:id,name', 'section:id,name', 'course:id,title']);
        return response()->json(['data' => $this->data($session, $role)], 201);
    }

    public function update(Request $request, string $organizationId, string $sessionId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        $role = $this->role($request, $organization);
        $session = LiveSession::query()->where('organization_id', $organization->id)->whereKey($sessionId)->firstOrFail();
        $this->authorizeManager($request, $organization, $session, $role);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['live', 'completed', 'cancelled'])],
            'recording_url' => ['nullable', 'url', 'max:2048', $this->httpsRule()],
        ]);
        $allowed = match ($session->status) {
            'scheduled' => ['live', 'cancelled'],
            'live' => ['completed', 'cancelled'],
            default => [],
        };
        abort_unless(in_array($validated['status'], $allowed, true), 409, 'The live-session status transition is not allowed.');
        abort_if(! empty($validated['recording_url']) && $validated['status'] !== 'completed', 422, 'A recording can only be attached when the session is completed.');
        $session->forceFill(['status' => $validated['status'], 'recording_url' => $validated['recording_url'] ?? null])->save();
        return response()->json(['data' => $this->data($session->fresh()->load(['classroom:id,name', 'section:id,name', 'course:id,title']), $role)]);
    }

    public function join(Request $request, string $organizationId, string $sessionId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('viewLiveSessions', $organization), 403);
        $role = $this->role($request, $organization);
        $session = LiveSession::query()->where('organization_id', $organization->id)->whereKey($sessionId)->firstOrFail();
        $this->authorizeParticipant($request, $organization, $session, $role);
        $now = now();
        abort_unless(in_array($session->status, ['scheduled', 'live'], true)
            && $session->starts_at->lte($now->copy()->addMinutes(15)) && $session->ends_at->gte($now), 409, 'Meeting access is not open.');
        DB::transaction(function () use ($organization, $session, $request, $now): void {
            $access = DB::table('live_session_accesses')->where('organization_id', $organization->id)
                ->where('live_session_id', $session->id)->where('user_id', $request->user()->id)->lockForUpdate()->first();
            if ($access) {
                DB::table('live_session_accesses')->where('id', $access->id)->update([
                    'access_count' => $access->access_count + 1, 'last_accessed_at' => $now, 'updated_at' => $now,
                ]);
            } else {
                DB::table('live_session_accesses')->insert([
                    'organization_id' => $organization->id, 'live_session_id' => $session->id,
                    'user_id' => $request->user()->id, 'access_count' => 1, 'first_accessed_at' => $now,
                    'last_accessed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        });
        return response()->json(['data' => ['meeting_url' => $session->meeting_url, 'ends_at' => $session->ends_at->toIso8601String()]]);
    }

    public function roster(Request $request, string $organizationId, string $sessionId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        $role = $this->role($request, $organization);
        $session = LiveSession::query()->where('organization_id', $organization->id)->whereKey($sessionId)->firstOrFail();
        $this->authorizeManager($request, $organization, $session, $role, true);
        return response()->json(['data' => $this->rosterQuery($organization, $session)->get()]);
    }

    public function recordAttendance(Request $request, string $organizationId, string $sessionId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        $role = $this->role($request, $organization);
        abort_unless($request->user()->can('manageAttendance', $organization), 403);
        $session = LiveSession::query()->where('organization_id', $organization->id)->whereKey($sessionId)->firstOrFail();
        $this->authorizeManager($request, $organization, $session, $role, true);
        abort_unless(in_array($session->status, ['live', 'completed'], true), 409, 'Attendance is available after a session has started.');
        $validated = $request->validate([
            'records' => ['required', 'array', 'min:1'], 'records.*.student_id' => ['required', 'uuid', 'distinct'],
            'records.*.status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
        ]);
        $roster = $this->rosterQuery($organization, $session)->get();
        $expected = $roster->pluck('id')->map(fn ($id): string => (string) $id)->sort()->values();
        $submitted = collect($validated['records'])->pluck('student_id')->map(fn ($id): string => (string) $id)->sort()->values();
        abort_if($expected->isEmpty(), 422, 'Enroll learners before recording session attendance.');
        abort_unless($expected->all() === $submitted->all(), 422, 'Attendance must include every active session participant.');
        $class = $session->class_id ? $organization->classes()->whereKey($session->class_id)->firstOrFail() : null;
        $label = mb_substr(($class?->name ?? $session->course?->title ?? 'Course').($session->section?->name ? ' · '.$session->section->name : '').' · '.$session->title, 0, 100);
        $date = $session->starts_at->timezone($organization->timezone)->toDateString();
        $present = collect($validated['records'])->whereIn('status', ['present', 'late'])->count();
        $rate = round($present * 100 / $submitted->count(), 2);
        $attendance = DB::transaction(function () use ($organization, $session, $request, $validated, $label, $date, $rate): AttendanceSession {
            $attendance = AttendanceSession::updateOrCreate(
                ['organization_id' => $organization->id, 'live_session_id' => $session->id],
                [
                    'class_id' => $session->class_id, 'section_id' => $session->section_id,
                    'recorded_by' => $request->user()->id, 'class_name' => $label,
                    'session_date' => $date, 'attendance_rate' => $rate,
                ],
            );
            foreach ($validated['records'] as $record) {
                AttendanceRecord::updateOrCreate(
                    ['organization_id' => $organization->id, 'attendance_session_id' => $attendance->id, 'student_id' => $record['student_id']],
                    ['recorded_by' => $request->user()->id, 'status' => $record['status']],
                );
            }
            return $attendance;
        });
        return response()->json(['data' => [
            'attendance_session_id' => $attendance->id, 'live_session_id' => $session->id,
            'session_date' => $attendance->session_date->toDateString(), 'attendance_rate' => (float) $attendance->attendance_rate,
            'records_count' => count($validated['records']),
        ]]);
    }

    private function rosterQuery(Organization $organization, LiveSession $session)
    {
        $query = DB::table('student_profiles as student')->where('student.organization_id', $organization->id)->where('student.status', 'active');
        if ($session->class_id) {
            $query->join('enrollments as audience_enrollment', function ($join) use ($session): void {
                $join->on('audience_enrollment.organization_id', '=', 'student.organization_id')
                    ->on('audience_enrollment.student_id', '=', 'student.id')->where('audience_enrollment.class_id', '=', $session->class_id)
                    ->where('audience_enrollment.status', '=', 'active');
                if ($session->section_id) $join->where('audience_enrollment.section_id', '=', $session->section_id);
            });
        }
        if ($session->course_id) {
            $query->join('course_enrollments as audience_enrollment', function ($join) use ($session): void {
                $join->on('audience_enrollment.organization_id', '=', 'student.organization_id')
                    ->on('audience_enrollment.student_id', '=', 'student.id')->where('audience_enrollment.course_id', '=', $session->course_id)
                    ->where('audience_enrollment.status', '=', 'active');
            });
        }
        $query->select('student.id', 'student.name', 'student.student_code')->distinct();
        return $query->orderBy('student.name');
    }

    private function authorizeManager(Request $request, Organization $organization, LiveSession $session, string $role, bool $attendance = false): void
    {
        abort_unless($request->user()->can($attendance ? 'manageAttendance' : 'manageLiveSessions', $organization), 403);
        if (in_array($role, ['owner', 'admin', 'educator'], true)) return;
        abort_unless($role === 'teacher', 403);
        $this->assertTeacherCanManageAudience($request, $organization, $session->class_id, $session->section_id, $session->course_id);
    }

    private function authorizeParticipant(Request $request, Organization $organization, LiveSession $session, string $role): void
    {
        if (in_array($role, ['owner', 'admin', 'educator'], true)) return;
        if ($role === 'teacher') {
            $this->assertTeacherCanJoinAudience($request, $organization, $session->class_id, $session->section_id, $session->course_id);
            return;
        }
        abort_unless($role === 'student', 403);
        $student = $organization->students()->where('status', 'active')->where('user_id', $request->user()->id)->firstOrFail();
        if ($session->class_id) {
            abort_unless(DB::table('enrollments')->where('organization_id', $organization->id)->where('class_id', $session->class_id)
                ->where('student_id', $student->id)->where('status', 'active')
                ->when($session->section_id, fn ($query) => $query->where('section_id', $session->section_id))->exists(), 404);
        } elseif ($session->course_id) {
            abort_unless($organization->courses()->where('status', 'active')->whereKey($session->course_id)->exists(), 404);
            abort_unless(DB::table('course_enrollments')->where('organization_id', $organization->id)->where('course_id', $session->course_id)
                ->where('student_id', $student->id)->where('status', 'active')->exists(), 404);
        }
    }

    private function assertTeacherCanManageAudience(Request $request, Organization $organization, ?string $classId, ?string $sectionId, ?string $courseId): void
    {
        if ($classId) {
            $scopes = app(TeacherClassScope::class)->assignmentScopes($request->user(), $organization) ?? collect();
            abort_unless($scopes->contains(fn ($scope) => $scope->class_id === $classId
                && ($scope->section_id === null || $scope->section_id === $sectionId)), 404);
        }
        if ($courseId) abort_unless(app(TeacherCourseScope::class)->courses($organization, $request->user())->whereKey($courseId)->exists(), 404);
    }

    private function assertTeacherCanJoinAudience(Request $request, Organization $organization, ?string $classId, ?string $sectionId, ?string $courseId): void
    {
        if ($classId) {
            $scopes = app(TeacherClassScope::class)->assignmentScopes($request->user(), $organization) ?? collect();
            abort_unless($scopes->contains(fn ($scope) => $scope->class_id === $classId
                && ($scope->section_id === null || $sectionId === null || $scope->section_id === $sectionId)), 404);
        }
        if ($courseId) abort_unless(app(TeacherCourseScope::class)->courses($organization, $request->user())->whereKey($courseId)->exists(), 404);
    }

    private function teacherScope(Builder $query, Request $request, Organization $organization): void
    {
        $scopes = app(TeacherClassScope::class)->assignmentScopes($request->user(), $organization) ?? collect();
        $courseIds = app(TeacherCourseScope::class)->courses($organization, $request->user())->pluck('courses.id');
        if ($scopes->isEmpty() && $courseIds->isEmpty()) {
            $query->whereRaw('1 = 0');
            return;
        }
        $query->where(function ($scope) use ($scopes, $courseIds): void {
            foreach ($scopes as $assignment) {
                $scope->orWhere(fn ($class) => $class->where('class_id', $assignment->class_id)
                    ->when($assignment->section_id !== null, fn ($section) => $section->where(fn ($audience) => $audience
                        ->where('section_id', $assignment->section_id)->orWhereNull('section_id'))));
            }
            if ($courseIds->isNotEmpty()) $scope->orWhereIn('course_id', $courseIds);
        });
    }

    private function data(LiveSession $session, string $role): array
    {
        $now = now();
        return [
            'id' => $session->id, 'title' => $session->title, 'description' => $session->description,
            'class_id' => $session->class_id, 'class_name' => $session->classroom?->name,
            'section_id' => $session->section_id, 'section_name' => $session->section?->name,
            'course_id' => $session->course_id, 'course_title' => $session->course?->title,
            'provider' => $session->provider, 'starts_at' => $session->starts_at->toIso8601String(),
            'ends_at' => $session->ends_at->toIso8601String(), 'status' => $session->status,
            'join_available' => in_array($role, ['owner', 'admin', 'educator', 'teacher', 'student'], true)
                && in_array($session->status, ['scheduled', 'live'], true)
                && $session->starts_at->lte($now->copy()->addMinutes(15)) && $session->ends_at->gte($now),
            'recording_url' => $session->status === 'completed' ? $session->recording_url : null,
        ];
    }

    private function organization(Request $request, string $organizationId): Organization
    {
        return $request->user()->organizations()->wherePivot('status', 'active')->where('organizations.status', 'active')
            ->where('organizations.id', $organizationId)->firstOrFail();
    }

    private function role(Request $request, Organization $organization): string
    {
        return (string) $organization->users()->where('users.id', $request->user()->id)->firstOrFail()->pivot->role;
    }

    private function httpsRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value !== null && parse_url($value, PHP_URL_SCHEME) !== 'https') $fail('Meeting and recording links must use secure HTTPS.');
        };
    }

    private function providerFromUrl(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return match (true) {
            str_contains($host, 'zoom.us') => 'zoom',
            str_contains($host, 'meet.google.com') => 'google_meet',
            str_contains($host, 'teams.microsoft.com') => 'microsoft_teams',
            default => 'external',
        };
    }
}
