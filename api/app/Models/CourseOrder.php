<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseOrder extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'course_id', 'student_id', 'buyer_user_id', 'amount_paise', 'status',
        'provider', 'provider_event_id', 'payment_method', 'transaction_reference', 'expires_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['amount_paise' => 'integer', 'expires_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }
}
