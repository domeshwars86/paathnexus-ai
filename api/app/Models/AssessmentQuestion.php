<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentQuestion extends Model
{
    use HasUuids;

    protected $fillable = ['organization_id', 'course_id', 'assessment_id', 'question_id', 'points', 'position'];

    protected function casts(): array
    {
        return ['points' => 'decimal:2'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
