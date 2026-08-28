<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    protected $fillable = [
        'name',
        'contact_name',
        'phone',
        'notes',
    ];

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ProjectTransaction::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(ProjectOffer::class);
    }

    public function packageItems(): HasMany
    {
        return $this->hasMany(WorkPackageItem::class);
    }
}
