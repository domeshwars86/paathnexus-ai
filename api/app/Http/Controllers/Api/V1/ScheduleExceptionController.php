<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Organization;
use App\Models\OrganizationSubjectOffering;
use App\Models\ScheduleException;
use App\Models\TimetableEntry;
use App\Services\TeacherClassScope;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleExceptionController extends Controller
{
    public function index(Request $request, string $organizationId, TeacherClassScope $teacherScope): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewAcademic', $organization), 403);
        $validated = $request->validate(['academic_year_id' => ['sometimes', 'uuid']]);
        $yearId = $validated['academic_year_id'] ?? $organization->academicYears()->where('status', 'active')->value('id');
        if (! $yearId) return response()->json(['data' => []]);
        abort_unless($organization->academicYears()->whereKey($yearId)->exists(), 404);

        $query = ScheduleException::query()->where('organization_id', $organization->id)->where('academic_year_id', $yearId)
            ->with(['organizationClass:id,name', 'section:id,name', 'subjectCatalog:id,name', 'teacherProfile:id,name']);
        $assignmentScopes = $teacherScope->assignmentScopes($request->user(), $organization);
        if ($assignmentScopes !== null) {
            if ($assignmentScopes->isEmpty()) $query->where('type', 'closure');
            else $query->where(function ($scopeQuery) use ($assignmentScopes): void {
                foreach ($assignmentScopes as $assignment) {
                    $scopeQuery->orWhere(function ($assignmentQuery) use ($assignment): void {
                        $assignmentQuery->where('class_id', $assignment->class_id);
                        if ($assignment->section_id !== null) {
                            $assignmentQuery->where(fn ($sectionQuery) => $sectionQuery->whereNull('section_id')->orWhere('section_id', $assignment->section_id));
                        }
                    });
                }
                $scopeQuery->orWhere('type', 'closure');
            });
        }

        return response()->json(['data' => $query->orderByDesc('exception_date')->limit(200)->get()->map(fn (ScheduleException $item): array => $this->exceptionData($item))]);
    }

    public function store(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);

        $validated = $request->validate([
            'academic_year_id' => ['required', 'uuid'],
            'exception_date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', 'in:closure,cancel,extra'],
            'title' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'class_id' => ['nullable', 'uuid', 'required_if:type,cancel,extra', 'prohibited_if:type,closure'],
            'section_id' => ['nullable', 'uuid', 'prohibited_if:type,closure'],
            'timetable_entry_id' => ['nullable', 'uuid', 'prohibited_unless:type,cancel'],
            'subject_id' => ['nullable', 'uuid', 'required_if:type,extra', 'prohibited_unless:type,extra'],
            'teacher_profile_id' => ['nullable', 'uuid', 'prohibited_if:type,closure', 'prohibited_unless:type,extra'],
            'starts_at' => ['nullable', 'date_format:H:i', 'required_if:type,extra', 'prohibited_unless:type,extra'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at', 'required_if:type,extra', 'prohibited_unless:type,extra'],
            'room' => ['nullable', 'string', 'max:80', 'prohibited_if:type,closure', 'prohibited_unless:type,extra'],
        ]);
        $year = $organization->academicYears()->whereKey($validated['academic_year_id'])->firstOrFail();
        $date = Carbon::parse($validated['exception_date'], $organization->timezone);
        abort_unless($date->toDateString() >= $year->starts_on->toDateString() && $date->toDateString() <= $year->ends_on->toDateString(), 422, 'The date must fall inside the selected academic year.');
        if ($validated['type'] === 'closure') {
            abort_if(ScheduleException::query()->where('organization_id', $organization->id)->where('academic_year_id', $year->id)
                ->whereDate('exception_date', $date->toDateString())->where('type', 'closure')->where('status', 'active')->exists(),
                422, 'An active school closure already exists for this date.');
        }

        $class = null;
        if (! empty($validated['class_id'])) {
            $class = $organization->classes()->where('status', 'active')->whereKey($validated['class_id'])->firstOrFail();
        }
        if (! empty($validated['section_id'])) {
            abort_unless($class && $class->sections()->where('status', 'active')->whereKey($validated['section_id'])->exists(), 404);
        }

        $entry = null;
        if (! empty($validated['timetable_entry_id'])) {
            $entry = $organization->timetableEntries()->where('academic_year_id', $year->id)->whereKey($validated['timetable_entry_id'])->firstOrFail();
            abort_unless($entry->class_id === $class?->id && $entry->weekday === $date->dayOfWeekIso, 422, 'The lesson does not match this class and date.');
            abort_unless($entry->section_id === ($validated['section_id'] ?? null), 422, 'The lesson does not match this section.');
        }

        if ($validated['type'] === 'extra') {
            $subject = $organization->subjects()->where('status', 'active')->whereKey($validated['subject_id'])->firstOrFail();
            $teacher = ! empty($validated['teacher_profile_id'])
                ? $organization->teacherProfiles()->where('status', 'active')->whereKey($validated['teacher_profile_id'])->firstOrFail()
                : null;
            $classHasOfferings = OrganizationSubjectOffering::query()->where('organization_id', $organization->id)
                ->where('class_id', $class->id)->where('academic_year_id', $year->id)->whereNull('ended_at')->exists();
            if ($classHasOfferings) {
                $matchingOffering = OrganizationSubjectOffering::query()->where('organization_id', $organization->id)
                    ->where('class_id', $class->id)->where('academic_year_id', $year->id)->where('subject_id', $subject->id)
                    ->whereNull('ended_at')->where(function ($query) use ($validated): void {
                        $query->whereNull('section_id');
                        if (! empty($validated['section_id'])) $query->orWhere('section_id', $validated['section_id']);
                    })->exists();
                abort_unless($matchingOffering, 422, 'This subject is not offered to the selected class or section for the academic year.');
            }
            abort_if(ScheduleException::query()->where('organization_id', $organization->id)->where('academic_year_id', $year->id)
                ->whereDate('exception_date', $date->toDateString())->where('type', 'closure')->where('status', 'active')->exists(),
                422, 'An extra lesson cannot be scheduled on a school closure.');
            $this->assertExtraLessonDoesNotConflict($organization, $year, $class, $validated, $date, $teacher);
            $validated['subject_id'] = $subject->id;
            $validated['teacher_profile_id'] = $teacher?->id;
        }

        $exception = ScheduleException::create([
            ...$validated,
            'organization_id' => $organization->id,
            'exception_date' => $date->toDateString(),
            'class_id' => $entry?->class_id ?? $class?->id,
            'section_id' => $entry?->section_id ?? ($validated['section_id'] ?? null),
            'timetable_entry_id' => $entry?->id,
            'status' => 'active',
            'created_by' => $request->user()->id,
        ])->load(['organizationClass:id,name', 'section:id,name', 'subjectCatalog:id,name', 'teacherProfile:id,name']);

        return response()->json(['data' => $this->exceptionData($exception)], 201);
    }

    public function cancel(Request $request, string $organizationId, string $exceptionId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $exception = ScheduleException::query()->where('organization_id', $organization->id)->whereKey($exceptionId)->firstOrFail();
        abort_unless($exception->status === 'active', 409, 'This schedule exception has already been cancelled.');
        $exception->update(['status' => 'cancelled', 'cancelled_by' => $request->user()->id, 'cancelled_at' => now()]);
        return response()->json(['data' => $this->exceptionData($exception->load(['organizationClass:id,name', 'section:id,name', 'subjectCatalog:id,name', 'teacherProfile:id,name']))]);
    }

    private function assertExtraLessonDoesNotConflict(Organization $organization, AcademicYear $year, $class, array $data, Carbon $date, $teacher): void
    {
        $cancelledEntryIds = ScheduleException::query()->where('organization_id', $organization->id)->where('academic_year_id', $year->id)
            ->whereDate('exception_date', $date->toDateString())->where('type', 'cancel')->where('status', 'active')
            ->whereNotNull('timetable_entry_id')->pluck('timetable_entry_id');
        $classDayCancellation = ScheduleException::query()->where('organization_id', $organization->id)->where('academic_year_id', $year->id)
            ->whereDate('exception_date', $date->toDateString())->where('type', 'cancel')->where('status', 'active')
            ->where('class_id', $class->id)->where(function ($query) use ($data): void {
                $query->whereNull('section_id');
                if (! empty($data['section_id'])) $query->orWhere('section_id', $data['section_id']);
            })->exists();
        if ($classDayCancellation) {
            $cancelledClassLessons = $organization->timetableEntries()->where('academic_year_id', $year->id)->where('class_id', $class->id)
                ->where('weekday', $date->dayOfWeekIso);
            if (! empty($data['section_id'])) $cancelledClassLessons->where(fn ($query) => $query->whereNull('section_id')->orWhere('section_id', $data['section_id']));
            $cancelledEntryIds = $cancelledEntryIds->merge($cancelledClassLessons->pluck('id'))->unique()->values();
        }
        if (! $classDayCancellation) {
            $weekly = $organization->timetableEntries()->where('academic_year_id', $year->id)->where('class_id', $class->id)
                ->where('weekday', $date->dayOfWeekIso)->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at']);
            if (! empty($data['section_id'])) $weekly->where(fn ($query) => $query->whereNull('section_id')->orWhere('section_id', $data['section_id']));
            if ($cancelledEntryIds->isNotEmpty()) $weekly->whereNotIn('id', $cancelledEntryIds);
            abort_if($weekly->exists(), 422, 'This extra lesson overlaps the weekly timetable.');
        }

        $sameDayExtras = ScheduleException::query()->where('organization_id', $organization->id)->where('academic_year_id', $year->id)
            ->whereDate('exception_date', $date->toDateString())->where('type', 'extra')->where('status', 'active')
            ->where('class_id', $class->id)->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at']);
        if (! empty($data['section_id'])) $sameDayExtras->where(fn ($query) => $query->whereNull('section_id')->orWhere('section_id', $data['section_id']));
        abort_if($sameDayExtras->exists(), 422, 'This class already has an extra lesson in that time slot.');

        if ($teacher) {
            $teacherWeekly = $organization->timetableEntries()->where('academic_year_id', $year->id)->where('teacher_profile_id', $teacher->id)
                ->where('weekday', $date->dayOfWeekIso)->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at']);
            if ($cancelledEntryIds->isNotEmpty()) $teacherWeekly->whereNotIn('id', $cancelledEntryIds);
            abort_if($teacherWeekly->exists(), 422, 'This teacher has a weekly lesson in that time slot.');
            abort_if(ScheduleException::query()->where('organization_id', $organization->id)->where('academic_year_id', $year->id)
                ->whereDate('exception_date', $date->toDateString())->where('type', 'extra')->where('status', 'active')
                ->where('teacher_profile_id', $teacher->id)->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])->exists(),
                422, 'This teacher already has an extra lesson in that time slot.');
        }
    }

    private function memberOrganization(Request $request, string $organizationId): Organization
    {
        return $request->user()->organizations()->wherePivot('status', 'active')
            ->where('organizations.status', 'active')->where('organizations.id', $organizationId)->firstOrFail();
    }

    private function exceptionData(ScheduleException $item): array
    {
        return [
            'id' => $item->id, 'academic_year_id' => $item->academic_year_id,
            'exception_date' => $item->exception_date->toDateString(), 'type' => $item->type,
            'title' => $item->title, 'notes' => $item->notes, 'class_id' => $item->class_id,
            'class_name' => $item->organizationClass?->name, 'section_id' => $item->section_id,
            'section_name' => $item->section?->name, 'timetable_entry_id' => $item->timetable_entry_id,
            'subject_id' => $item->subject_id, 'subject' => $item->subjectCatalog?->name,
            'teacher_profile_id' => $item->teacher_profile_id, 'teacher_name' => $item->teacherProfile?->name,
            'starts_at' => $item->starts_at ? substr((string) $item->starts_at, 0, 5) : null,
            'ends_at' => $item->ends_at ? substr((string) $item->ends_at, 0, 5) : null,
            'room' => $item->room, 'status' => $item->status,
            'cancelled_at' => $item->cancelled_at?->toIso8601String(),
        ];
    }
}
