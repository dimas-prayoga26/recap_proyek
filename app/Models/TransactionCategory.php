<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionCategory extends Model
{
    protected $fillable = [
        'name',
        'type',
        'status',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(ProjectTransaction::class);
    }
}
