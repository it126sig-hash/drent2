<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhysicalCheckOtp extends Model
{
    protected $fillable = [
        'physical_check_id',
        'email',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
        'requested_ip',
        'requested_user_agent',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function physicalCheck()
    {
        return $this->belongsTo(PhysicalCheck::class);
    }
}
