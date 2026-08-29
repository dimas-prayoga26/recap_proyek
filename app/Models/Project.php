<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'status',
        'description',
    ];

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }

    public function paymentGroups(): HasMany
    {
        return $this->hasMany(PaymentGroup::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ProjectTransaction::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(ProjectOffer::class);
    }

    public function activeSelections(): HasMany
    {
        return $this->hasMany(ActiveProjectSelection::class);
    }
}
