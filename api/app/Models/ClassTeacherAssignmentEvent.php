<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassTeacherAssignmentEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'assignment_id', 'action', 'from_teacher_profile_id', 'to_teacher_profile_id', 'actor_id',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ClassTeacherAssignment::class, 'assignment_id');
    }

    public function fromTeacher(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class, 'from_teacher_profile_id');
    }

    public function toTeacher(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class, 'to_teacher_profile_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
