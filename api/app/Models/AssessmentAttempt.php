<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAttempt extends Model
{
    use HasUuids;

    protected $fillable = ['organization_id', 'course_id', 'assessment_id', 'student_id', 'attempt_number', 'status', 'review_status', 'score', 'max_points', 'question_order', 'started_at', 'expires_at', 'submitted_at'];

    protected function casts(): array
    {
        return ['score' => 'decimal:2', 'max_points' => 'decimal:2', 'question_order' => 'array', 'started_at' => 'datetime', 'expires_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class, 'attempt_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }
}
