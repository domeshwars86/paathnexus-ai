<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentStudyPlan extends Model
{
    use HasUuids;

    protected $fillable = ['organization_id', 'student_id', 'course_id', 'title', 'scheduled_at', 'duration_minutes', 'status', 'completed_at'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'duration_minutes' => 'integer'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
