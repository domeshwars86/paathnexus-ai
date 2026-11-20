<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssignmentSubmission extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'course_id', 'assignment_id', 'student_id', 'submission_text',
        'submitted_at', 'score', 'feedback', 'status', 'graded_by', 'graded_at',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'graded_at' => 'datetime', 'score' => 'decimal:2'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssignmentAttachment::class, 'submission_id');
    }
}
