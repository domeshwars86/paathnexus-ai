<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSubjectOffering extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'class_id', 'section_id', 'academic_year_id', 'subject_id', 'created_by', 'ended_at',
    ];

    protected function casts(): array
    {
        return ['ended_at' => 'datetime'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(OrganizationSubject::class, 'subject_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(OrganizationSection::class, 'section_id');
    }
}
