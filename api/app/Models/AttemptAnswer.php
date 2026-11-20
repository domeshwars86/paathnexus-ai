<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptAnswer extends Model
{
    use HasUuids;

    protected $fillable = ['organization_id', 'course_id', 'assessment_id', 'attempt_id', 'question_id', 'selected_option_ids', 'response_text', 'grader_feedback', 'rubric_scores', 'graded_by', 'graded_at', 'is_correct', 'points_awarded'];

    protected function casts(): array
    {
        return ['selected_option_ids' => 'array', 'rubric_scores' => 'array', 'is_correct' => 'boolean', 'points_awarded' => 'decimal:2', 'graded_at' => 'datetime'];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class, 'attempt_id');
    }
}
