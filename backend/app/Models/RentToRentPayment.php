<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentToRentPayment extends Model
{
    protected $fillable = [
        'rent_to_rent_bill_id',
        'payment_account_id',
        'amount',
        'status',
        'catatan',
        'paid_at',
        'voided_at',
        'void_reason',
        'void_requested_by',
        'void_requested_at',
        'void_approved_by',
        'void_approved_at',
        'void_rejected_by',
        'void_rejected_at',
        'void_rejection_note',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
        'void_requested_at' => 'datetime',
        'void_approved_at' => 'datetime',
        'void_rejected_at' => 'datetime',
    ];

    public function bill()
    {
        return $this->belongsTo(RentToRentBill::class, 'rent_to_rent_bill_id');
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function allocations()
    {
        return $this->hasMany(RentToRentPaymentAllocation::class);
    }
}
