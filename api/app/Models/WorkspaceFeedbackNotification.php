<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceFeedbackNotification extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['organization_id', 'feedback_id', 'user_id', 'read_at', 'created_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(SupportFeedback::class, 'feedback_id');
    }
}
