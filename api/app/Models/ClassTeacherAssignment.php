<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassTeacherAssignment extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'class_id', 'teacher_profile_id', 'academic_year_id', 'section_id', 'assigned_by', 'ended_at',
    ];

    protected function casts(): array
    {
        return ['ended_at' => 'datetime'];
    }

    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class);
    }

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
}
