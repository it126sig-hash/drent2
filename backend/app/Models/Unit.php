<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use HasFactory, SoftDeletes;

    // TODO: konfirmasi strategi arsip (soft-delete vs tabel terpisah vs is_archived flag)
    // Keputusan ini belum final per 2026-05-09.

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'rental_owner_id',
        'tipe',
        'merk',
        'tahun',
        'no_polisi',
        'harga_1_hari',
        'harga_1_minggu',
        'harga_1_bulan',
        'modal_1_hari',
        'modal_1_minggu',
        'modal_1_bulan',
        'status',
        'catatan',
    ];

    protected $casts = [
        'tahun' => 'integer',
        'harga_1_hari' => 'integer',
        'harga_1_minggu' => 'integer',
        'harga_1_bulan' => 'integer',
        'modal_1_hari' => 'integer',
        'modal_1_minggu' => 'integer',
        'modal_1_bulan' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function rentalOwner()
    {
        return $this->belongsTo(RentalOwner::class);
    }

    public function photos()
    {
        return $this->hasMany(UnitPhoto::class);
    }
}
