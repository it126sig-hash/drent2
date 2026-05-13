<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    // TODO: konfirmasi strategi arsip (soft-delete vs tabel terpisah vs is_archived flag)
    // Keputusan ini belum final per 2026-05-09.

    protected $fillable = [
        'tenant_id',
        'nama',
        'kontak_1',
        'kontak_2',
        'alamat',
        'kota',
        'status',
        'has_apply_member',
        'catatan'
    ];

    protected $casts = [
        'has_apply_member' => 'boolean'
    ];

    public function member()
    {
        return $this->hasOne(Member::class);
    }
}
