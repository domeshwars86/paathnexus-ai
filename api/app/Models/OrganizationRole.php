<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationRole extends Model
{
    use HasUuids;

    protected $fillable = ['organization_id', 'base_role', 'name', 'slug', 'permissions'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'archived_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
