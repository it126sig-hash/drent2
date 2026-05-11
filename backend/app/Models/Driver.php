<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Driver extends Model
{
    use HasFactory, SoftDeletes;

    // TODO: konfirmasi strategi arsip (soft-delete vs tabel terpisah vs is_archived flag)
    // Keputusan ini belum final per 2026-05-09.

    protected $fillable = [
        'tenant_id', 'branch_id', 'user_id',
        'nama', 'alamat', 'kota', 'no_sim',
        'kontak_1', 'kontak_2', 'saldo',
        'status', 'is_tetap', 'catatan',
    ];

    protected $casts = [
        'is_tetap' => 'boolean',
        'saldo'    => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
