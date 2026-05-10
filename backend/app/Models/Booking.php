<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'customer_id',
        'kode_booking',
        'status',
        'harga_dealing',
        'dp',
        'rekening_dp_id',
        'tujuan',
        'alamat_penjemputan',
        'catatan',
    ];

    // TODO: konfirmasi strategi arsip (soft-delete vs tabel terpisah vs is_archived flag)
    // Keputusan ini belum final per 2026-05-10.

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function bookingDetails()
    {
        return $this->hasMany(BookingDetail::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
