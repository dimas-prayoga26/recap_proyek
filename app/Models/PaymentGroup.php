<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGroup extends Model
{
    protected $fillable = [
        'project_id',
        'work_item_id',
        'code',
        'name',
        'total_amount',
        'offer_rupiah_snapshot',
        'offer_usd_snapshot',
        'total_terms',
        'fixed_total_terms',
        'paid_terms',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
            'offer_rupiah_snapshot' => 'integer',
            'offer_usd_snapshot' => 'decimal:2',
            'total_terms' => 'integer',
            'fixed_total_terms' => 'integer',
            'paid_terms' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ProjectTransaction::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(PaymentTerm::class);
    }
}
