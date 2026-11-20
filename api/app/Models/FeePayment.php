<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeePayment extends Model
{
    use HasUuids;

    protected $fillable = ['organization_id', 'invoice_id', 'amount_paise', 'method', 'transaction_reference', 'idempotency_key', 'receipt_number', 'recorded_by', 'received_at', 'source', 'provider', 'provider_event_id'];

    protected function casts(): array
    {
        return ['amount_paise' => 'integer', 'received_at' => 'immutable_datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class);
    }
}
