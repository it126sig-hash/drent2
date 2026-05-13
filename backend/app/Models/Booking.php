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
        'created_by',
        'kode_booking',
        'status',
        'lama_sewa',
        'paket_sewa',
        'harga_dealing',
        'dp',
        'rekening_dp_id',
        'tujuan',
        'kota',
        'alamat_penjemputan',
        'catatan',
        'confirmed_at',
        'confirmed_by',
        'handled_at',
        'handled_by',
        'checked_out_at',
        'checked_out_by',
        'returned_at',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'lama_sewa' => 'integer',
        'confirmed_at' => 'datetime',
        'handled_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'returned_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // TODO: konfirmasi strategi arsip (soft-delete vs tabel terpisah vs is_archived flag)
    // Keputusan ini belum final per 2026-05-10.

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function checkedOutBy()
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
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

    public function payments()
    {
        return $this->hasMany(BookingPayment::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function physicalChecks()
    {
        return $this->hasMany(PhysicalCheck::class);
    }
}
