<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'path',
        'label',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
