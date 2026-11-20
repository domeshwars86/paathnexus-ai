<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\AttendanceRecord;
use App\Models\OrganizationClass;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Services\TeacherClassScope;
use App\Services\TeacherCourseScope;
use App\Services\AttendanceSessionScope;

class AttendanceSessionController extends Controller
{
    public function index(Request $request, string $organizationId, TeacherClassScope $teacherScope, TeacherCourseScope $teacherCourseScope, AttendanceSessionScope $attendanceScope): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewAttendance', $organization) && $request->user()->can('viewAcademic', $organization), 403);

        $query = $attendanceScope->forMember($organization, $request->user(), $teacherScope, $teacherCourseScope)
            ->orderByDesc('session_date')
            ->orderByDesc('updated_at')
            ->limit(30);
        if ($request->user()->can('viewAcademic', $organization)) {
            $query->with('records.student:id,name,student_code');
        }
        $sessions = $query->get()
            ->map(fn (AttendanceSession $session): array => $this->data($session));

        return response()->json(['data' => $sessions]);
    }

    public function store(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAttendance', $organization) && $request->user()->can('manageAcademic', $organization), 403);

        $validated = $request->validate([
            'class_name' => ['required', 'string', 'max:100'],
            'attendance_rate' => ['required', 'numeric', 'between:0,100'],
        ]);

        $sessionDate = Carbon::now($organization->timezone)->toDateString();
        $session = $organization->attendanceSessions()->updateOrCreate(
            ['class_name' => $validated['class_name'], 'session_date' => $sessionDate],
            ['recorded_by' => $request->user()->id, 'attendance_rate' => $validated['attendance_rate']],
        );

        return response()->json(['data' => $this->data($session)], $session->wasRecentlyCreated ? 201 : 200);
    }

    public function markClass(Request $request, string $organizationId, string $classId, TeacherClassScope $teacherScope): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAttendance', $organization), 403);
        $class = $organization->classes()->where('status', 'active')->whereKey($classId)->firstOrFail();
        $validated = $request->validate([
            'section_id' => ['nullable', 'uuid'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'uuid', 'distinct'],
            'records.*.status' => ['required', 'string', 'in:present,absent,late,excused'],
        ]);

        $section = null;
        if (! empty($validated['section_id'])) {
            $section = $class->sections()->where('status', 'active')->whereKey($validated['section_id'])->firstOrFail();
        }
        $assignmentScopes = $teacherScope->assignmentScopes($request->user(), $organization);
        if ($assignmentScopes !== null) {
            $hasWholeClass = $assignmentScopes->contains(fn ($scope) => $scope->class_id === $class->id && $scope->section_id === null);
            $hasSection = $section && $assignmentScopes->contains(fn ($scope) => $scope->class_id === $class->id && $scope->section_id === $section->id);
            abort_unless($hasWholeClass || $hasSection, 404);
        }

        $rosterQuery = $class->students()
            ->where('student_profiles.status', 'active')
            ->wherePivot('status', 'active');
        if ($section) $rosterQuery->wherePivot('section_id', $section->id);
        $rosterIds = $rosterQuery->pluck('student_profiles.id')
            ->map(fn ($id): string => (string) $id)
            ->sort()->values();
        $submittedIds = collect($validated['records'])
            ->pluck('student_id')->map(fn ($id): string => (string) $id)->sort()->values();
        abort_if($rosterIds->isEmpty(), 422, 'Enroll students in this class before recording attendance.');
        abort_if($rosterIds->all() !== $submittedIds->all(), 422, 'Attendance must include every active student in this class.');

        $sessionDate = Carbon::now($organization->timezone)->toDateString();
        $sessionLabel = $class->name.($section ? ' · '.$section->name : '');
        $session = DB::transaction(function () use ($class, $section, $sessionLabel, $organization, $request, $validated, $sessionDate): AttendanceSession {
            $countsAsPresent = collect($validated['records'])->whereIn('status', ['present', 'late'])->count();
            $rate = round(($countsAsPresent / count($validated['records'])) * 100, 2);
            $session = $organization->attendanceSessions()->updateOrCreate(
                ['class_name' => $sessionLabel, 'session_date' => $sessionDate],
                ['class_id' => $class->id, 'section_id' => $section?->id, 'recorded_by' => $request->user()->id, 'attendance_rate' => $rate],
            );

            foreach ($validated['records'] as $record) {
                AttendanceRecord::updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'attendance_session_id' => $session->id,
                        'student_id' => $record['student_id'],
                    ],
                    ['recorded_by' => $request->user()->id, 'status' => $record['status']],
                );
            }

            return $session;
        });

        $records = AttendanceRecord::query()
            ->where('organization_id', $organization->id)
            ->where('attendance_session_id', $session->id)
            ->with('student:id,name,student_code')
            ->orderBy('student_id')
            ->get()
            ->map(fn (AttendanceRecord $record): array => [
                'student_id' => $record->student_id,
                'student_name' => $record->student->name,
                'student_code' => $record->student->student_code,
                'status' => $record->status,
            ]);

        return response()->json(['data' => [...$this->data($session->load('records.student:id,name,student_code')), 'records' => $records]], $session->wasRecentlyCreated ? 201 : 200);
    }

    private function memberOrganization(Request $request, string $organizationId): Organization
    {
        return $request->user()->organizations()
            ->wherePivot('status', 'active')
            ->where('organizations.status', 'active')
            ->where('organizations.id', $organizationId)
            ->firstOrFail();
    }

    private function data(AttendanceSession $session): array
    {
        $data = [
            'id' => $session->id,
            'class_id' => $session->class_id,
            'section_id' => $session->section_id,
            'live_session_id' => $session->live_session_id,
            'class_name' => $session->class_name,
            'session_date' => $session->session_date->toDateString(),
            'attendance_rate' => (float) $session->attendance_rate,
            'updated_at' => $session->updated_at?->toIso8601String(),
        ];

        if ($session->relationLoaded('records')) {
            $data['records'] = $session->records->map(fn (AttendanceRecord $record): array => [
                'student_id' => $record->student_id,
                'student_name' => $record->student?->name,
                'student_code' => $record->student?->student_code,
                'status' => $record->status,
            ])->values();
        }

        return $data;
    }
}
