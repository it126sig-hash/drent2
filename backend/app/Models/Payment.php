<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id',
        'parent_payment_id',
        'payment_account_id',
        'amount',
        'paid_at',
        'created_by',
        'status',
        'void_reason',
        'void_requested_by',
        'void_requested_at',
        'void_approved_by',
        'void_approved_at',
        'void_rejected_by',
        'void_rejected_at',
        'void_rejection_note',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
        'void_requested_at' => 'datetime',
        'void_approved_at' => 'datetime',
        'void_rejected_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bookingPayments()
    {
        return $this->hasMany(BookingPayment::class, 'invoice_payment_id');
    }

    public function parentPayment()
    {
        return $this->belongsTo(self::class, 'parent_payment_id');
    }

    public function childPayments()
    {
        return $this->hasMany(self::class, 'parent_payment_id');
    }

    public function voidRequester()
    {
        return $this->belongsTo(User::class, 'void_requested_by');
    }

    public function voidApprover()
    {
        return $this->belongsTo(User::class, 'void_approved_by');
    }

    public function voidRejecter()
    {
        return $this->belongsTo(User::class, 'void_rejected_by');
    }
}
