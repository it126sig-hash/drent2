<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RentalOwner extends Model
{
    use HasFactory, SoftDeletes;

    // TODO: konfirmasi strategi arsip (soft-delete vs tabel terpisah vs is_archived flag)
    // Keputusan ini belum final per 2026-05-09.

    protected $fillable = [
        'tenant_id',
        'nama',
        'kontak_1',
        'kontak_2',
        'alamat',
        'kota',
        'bank',
        'no_rek',
        'atas_nama',
        'is_owner',
    ];

    protected $casts = [
        'is_owner' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function rentToRentDebts()
    {
        return $this->hasMany(RentToRentDebt::class);
    }

    public function rentToRentBills()
    {
        return $this->hasMany(RentToRentBill::class);
    }
}
