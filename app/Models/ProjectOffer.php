<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectOffer extends Model
{
    protected $fillable = [
        'project_id',
        'project_area_id',
        'vendor_id',
        'work_item_id',
        'project_name',
        'area',
        'pekerjaan',
        'brand',
        'penawaran_usd',
        'penawaran_rupiah',
        'catatan',
    ];

    protected $attributes = [
        'project_name' => 'Project Kemang',
    ];

    protected function casts(): array
    {
        return [
            'penawaran_usd' => 'decimal:2',
            'penawaran_rupiah' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectArea(): BelongsTo
    {
        return $this->belongsTo(ProjectArea::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }
}
