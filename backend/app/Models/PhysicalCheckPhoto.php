<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhysicalCheckPhoto extends Model
{
    protected $fillable = [
        'physical_check_id',
        'section',
        'path',
        'annotated_path',
        'notes',
    ];

    public function physicalCheck()
    {
        return $this->belongsTo(PhysicalCheck::class);
    }
}
