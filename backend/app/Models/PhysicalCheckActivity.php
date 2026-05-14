<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhysicalCheckActivity extends Model
{
    protected $fillable = [
        'physical_check_id',
        'user_id',
        'actor_type',
        'event',
        'context',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function physicalCheck()
    {
        return $this->belongsTo(PhysicalCheck::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
