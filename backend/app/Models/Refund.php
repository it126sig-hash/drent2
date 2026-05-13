<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = [
        'booking_id',
        'payment_account_id',
        'amount',
        'keterangan',
        'refunded_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'refunded_at' => 'datetime',
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
}
