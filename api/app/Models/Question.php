<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasUuids;

    protected $fillable = ['organization_id', 'course_id', 'subject_id', 'topic', 'type', 'prompt', 'explanation', 'grading_rubric', 'difficulty', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['grading_rubric' => 'array'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('position');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
