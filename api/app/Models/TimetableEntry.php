<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'class_id', 'section_id', 'academic_year_id', 'subject', 'subject_id',
        'teacher_profile_id', 'weekday', 'starts_at', 'ends_at', 'room',
    ];

    public function organizationClass(): BelongsTo
    {
        return $this->belongsTo(OrganizationClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(OrganizationSection::class, 'section_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
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
