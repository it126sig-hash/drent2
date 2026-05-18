<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentToRentPaymentAllocation extends Model
{
    protected $fillable = [
        'rent_to_rent_payment_id',
        'rent_to_rent_bill_item_id',
        'rent_to_rent_debt_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function payment()
    {
        return $this->belongsTo(RentToRentPayment::class, 'rent_to_rent_payment_id');
    }

    public function billItem()
    {
        return $this->belongsTo(RentToRentBillItem::class, 'rent_to_rent_bill_item_id');
    }

    public function debt()
    {
        return $this->belongsTo(RentToRentDebt::class, 'rent_to_rent_debt_id');
    }
}
