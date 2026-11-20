<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuardianStudentLink extends Model
{
    use HasUuids;

    protected $fillable = ['organization_id', 'student_id', 'guardian_user_id', 'relationship', 'status', 'linked_by', 'linked_at', 'ended_at'];

    protected function casts(): array
    {
        return ['linked_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }
}
