<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleException extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'academic_year_id', 'exception_date', 'type', 'title', 'notes',
        'class_id', 'section_id', 'timetable_entry_id', 'subject_id', 'teacher_profile_id',
        'starts_at', 'ends_at', 'room', 'status', 'created_by', 'cancelled_by', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'exception_date' => 'date:Y-m-d',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function organizationClass(): BelongsTo
    {
        return $this->belongsTo(OrganizationClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(OrganizationSection::class, 'section_id');
    }

    public function timetableEntry(): BelongsTo
    {
        return $this->belongsTo(TimetableEntry::class);
    }

    public function subjectCatalog(): BelongsTo
    {
        return $this->belongsTo(OrganizationSubject::class, 'subject_id');
    }

    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class, 'teacher_profile_id');
    }
}
