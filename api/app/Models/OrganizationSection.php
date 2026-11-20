<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSection extends Model
{
    use HasUuids;

    protected $table = 'organization_sections';

    protected $fillable = ['organization_id', 'class_id', 'name', 'status'];

    public function organizationClass(): BelongsTo
    {
        return $this->belongsTo(OrganizationClass::class, 'class_id');
    }
}
