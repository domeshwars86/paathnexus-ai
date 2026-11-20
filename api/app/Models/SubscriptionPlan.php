<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'name', 'audience', 'description', 'amount_paise', 'billing_interval', 'entitlements', 'is_active'];

    protected function casts(): array
    {
        return ['amount_paise' => 'integer', 'entitlements' => 'array', 'is_active' => 'boolean'];
    }
}
