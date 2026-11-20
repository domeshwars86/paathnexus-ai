<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseLesson extends Model
{
    use HasUuids;

    protected $fillable = ['organization_id', 'course_id', 'title', 'content', 'resource_type', 'resource_url', 'position', 'status', 'created_by'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
