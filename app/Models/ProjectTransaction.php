<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTransaction extends Model
{
    protected $fillable = [
        'project_id',
        'transaction_category_id',
        'work_item_id',
        'vendor_id',
        'payment_group_id',
        'type',
        'amount',
        'recorded_at',
        'payment_number',
        'payment_total',
        'receipt_total',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'recorded_at' => 'date',
            'payment_number' => 'integer',
            'payment_total' => 'integer',
            'receipt_total' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class, 'transaction_category_id');
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function paymentGroup(): BelongsTo
    {
        return $this->belongsTo(PaymentGroup::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectTransactionAttachment::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ProjectTransactionAllocation::class);
    }
}
