<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'slug', 'type', 'status', 'locale', 'timezone', 'settings',
    ];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'custom_role_id', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function customRoles(): HasMany
    {
        return $this->hasMany(OrganizationRole::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(OrganizationTask::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(OrganizationClass::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(StudentProfile::class);
    }

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(OrganizationSubject::class);
    }

    public function teacherProfiles(): HasMany
    {
        return $this->hasMany(TeacherProfile::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
