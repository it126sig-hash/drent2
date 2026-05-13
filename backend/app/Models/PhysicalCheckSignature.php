<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhysicalCheckSignature extends Model
{
    protected $fillable = [
        'physical_check_id',
        'signer_type',
        'signer_name',
        'signature_path',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function physicalCheck()
    {
        return $this->belongsTo(PhysicalCheck::class);
    }
}
