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
        'paid_at',
        'voided_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
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

    public function allocations()
    {
        return $this->hasMany(RentToRentPaymentAllocation::class);
    }
}
