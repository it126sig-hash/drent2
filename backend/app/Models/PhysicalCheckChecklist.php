<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhysicalCheckChecklist extends Model
{
    protected $fillable = [
        'physical_check_id',
        'physical_check_item_id',
        'item_label',
        'is_present',
        'notes',
    ];

    protected $casts = [
        'is_present' => 'boolean',
    ];

    public function physicalCheck()
    {
        return $this->belongsTo(PhysicalCheck::class);
    }

    public function item()
    {
        return $this->belongsTo(PhysicalCheckItem::class, 'physical_check_item_id');
    }
}
