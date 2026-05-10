<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookingDetail extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'booking_id',
        'unit_id',
        'unit_placeholder',
        'driver_id',
        'tgl_sewa',
        'tgl_kembali',
        'harga_mobil',
        'diskon_mobil',
        'detail_type',
        'status',
    ];

    // TODO: konfirmasi strategi arsip (soft-delete vs tabel terpisah vs is_archived flag)
    // Keputusan ini belum final per 2026-05-10.

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function costs()
    {
        return $this->hasMany(BookingCost::class);
    }
}
