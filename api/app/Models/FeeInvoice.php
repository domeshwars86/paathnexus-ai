<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeInvoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'student_id', 'invoice_number', 'title', 'description',
        'amount_paise', 'due_on', 'status', 'issued_by', 'voided_at',
    ];

    protected function casts(): array
    {
        return ['amount_paise' => 'integer', 'due_on' => 'date', 'voided_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FeePayment::class, 'invoice_id');
    }
}
