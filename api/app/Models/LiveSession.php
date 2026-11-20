<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'class_id', 'section_id', 'course_id', 'created_by', 'title', 'description',
        'starts_at', 'ends_at', 'provider', 'meeting_url', 'recording_url', 'status',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(OrganizationClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(OrganizationSection::class, 'section_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}
