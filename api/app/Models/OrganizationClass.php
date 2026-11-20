<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationClass extends Model
{
    use HasUuids;

    protected $table = 'organization_classes';

    protected $fillable = ['organization_id', 'name', 'grade', 'section', 'status'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(StudentProfile::class, 'enrollments', 'class_id', 'student_id')
            ->withPivot(['organization_id', 'section_id', 'status', 'enrolled_on'])
            ->withTimestamps();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(OrganizationSection::class, 'class_id');
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class, 'class_id');
    }
}
