<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkItem extends Model
{
    protected $fillable = [
        'project_id',
        'vendor_id',
        'name',
        'package_name',
        'brand',
        'offer_rupiah',
        'offer_usd',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'offer_rupiah' => 'integer',
            'offer_usd' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ProjectTransaction::class);
    }

    public function paymentGroups(): HasMany
    {
        return $this->hasMany(PaymentGroup::class);
    }

    public function packageItems(): HasMany
    {
        return $this->hasMany(WorkPackageItem::class)->orderBy('sort_order');
    }
}
