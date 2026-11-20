<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assessment extends Model
{
    use HasUuids;

    protected $fillable = ['organization_id', 'course_id', 'title', 'instructions', 'duration_minutes', 'attempts_allowed', 'randomize_questions', 'randomized_question_count', 'opens_at', 'closes_at', 'status', 'published_at', 'created_by'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'opens_at' => 'datetime', 'closes_at' => 'datetime', 'randomize_questions' => 'boolean', 'randomized_question_count' => 'integer'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class)->orderBy('position');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class);
    }
}
