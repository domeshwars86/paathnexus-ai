<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformContentModeration extends Model
{
    protected $fillable = ['content_type', 'content_id', 'organization_id', 'status', 'reason', 'moderated_by', 'moderated_at'];

    protected function casts(): array
    {
        return ['moderated_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
