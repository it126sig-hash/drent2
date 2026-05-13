<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class City extends Model
{
    use SoftDeletes;

    // TODO: konfirmasi strategi arsip (soft-delete vs tabel terpisah vs is_archived flag)
    protected $fillable = [
        'tenant_id',
        'nama',
        'provinsi',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
