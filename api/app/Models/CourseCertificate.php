<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseCertificate extends Model
{
    use HasUuids;

    protected $fillable = ['organization_id', 'course_id', 'student_id', 'certificate_code', 'completed_lessons', 'total_lessons', 'issued_at'];

    protected function casts(): array
    {
        return ['issued_at' => 'immutable_datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }
}
