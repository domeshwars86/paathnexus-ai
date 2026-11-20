<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasUuids;

    protected $fillable = ['organization_id', 'class_id', 'section_id', 'student_id', 'status', 'enrolled_on'];

    public function section()
    {
        return $this->belongsTo(OrganizationSection::class, 'section_id');
    }

    protected function casts(): array
    {
        return ['enrolled_on' => 'date:Y-m-d'];
    }
}
