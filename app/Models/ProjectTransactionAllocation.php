<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTransactionAllocation extends Model
{
    protected $fillable = [
        'project_transaction_id',
        'work_item_id',
        'payment_group_id',
        'payment_term_id',
        'amount',
        'payment_number',
        'role',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'payment_number' => 'integer',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(ProjectTransaction::class, 'project_transaction_id');
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    public function paymentGroup(): BelongsTo
    {
        return $this->belongsTo(PaymentGroup::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }
}
