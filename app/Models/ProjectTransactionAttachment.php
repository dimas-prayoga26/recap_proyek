<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTransactionAttachment extends Model
{
    protected $fillable = [
        'project_transaction_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(ProjectTransaction::class, 'project_transaction_id');
    }
}
