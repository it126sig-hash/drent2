<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhysicalCheckSection extends Model
{
    protected $fillable = [
        'physical_check_id',
        'section',
        'notes',
    ];

    public function physicalCheck()
    {
        return $this->belongsTo(PhysicalCheck::class);
    }
}
