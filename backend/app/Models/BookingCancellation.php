<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingCancellation extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'booking_id',
        'ada_refund',
        'nominal_refund',
        'bank_refund',
        'no_rek_refund',
        'nama_rek_refund',
        'catatan_refund',
        'sudah_bayar_refund',
        'payment_account_id',
        'dibayar_at',
        'dibayar_oleh',
        'created_by',
    ];

    protected $casts = [
        'ada_refund'        => 'boolean',
        'sudah_bayar_refund' => 'boolean',
        'dibayar_at'        => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dibayarOleh()
    {
        return $this->belongsTo(User::class, 'dibayar_oleh');
    }
}
