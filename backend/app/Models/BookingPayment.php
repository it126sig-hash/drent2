<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingPayment extends Model
{
    protected $fillable = [
        'booking_id',
        'payment_account_id',
        'amount',
        'payment_type',
        'catatan',
        'paid_at',
        'reallocated_from_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function reallocatedFrom()
    {
        return $this->belongsTo(self::class, 'reallocated_from_id');
    }

    public function reallocations()
    {
        return $this->hasMany(self::class, 'reallocated_from_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
