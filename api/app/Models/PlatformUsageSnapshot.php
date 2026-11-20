<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformUsageSnapshot extends Model
{
    protected $fillable = [
        'snapshot_date', 'organization_count', 'active_organization_count', 'suspended_organization_count',
        'active_membership_count', 'active_student_count', 'active_course_count', 'active_subscription_count',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date:Y-m-d',
            'organization_count' => 'integer', 'active_organization_count' => 'integer',
            'suspended_organization_count' => 'integer', 'active_membership_count' => 'integer',
            'active_student_count' => 'integer', 'active_course_count' => 'integer',
            'active_subscription_count' => 'integer',
        ];
    }
}
