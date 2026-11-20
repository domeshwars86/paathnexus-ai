<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiUsageRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'user_id', 'feature', 'status', 'input_tokens', 'output_tokens', 'model', 'failure_code',
    ];
}
