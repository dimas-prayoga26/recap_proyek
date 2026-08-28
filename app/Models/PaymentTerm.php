<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentTerm extends Model
{
    protected $fillable = [
        'payment_group_id',
        'payment_number',
        'amount',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'payment_number' => 'integer',
            'amount' => 'integer',
            'paid_at' => 'date',
        ];
    }

    public function paymentGroup(): BelongsTo
    {
        return $this->belongsTo(PaymentGroup::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ProjectTransactionAllocation::class);
    }
}
