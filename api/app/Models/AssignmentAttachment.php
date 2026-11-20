<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentAttachment extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'course_id', 'assignment_id', 'submission_id', 'uploaded_by',
        'disk', 'storage_path', 'original_name', 'mime_type', 'size_bytes',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class);
    }
}
