<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentToRentBillItem extends Model
{
    protected $fillable = [
        'rent_to_rent_bill_id',
        'rent_to_rent_debt_id',
        'booking_detail_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function bill()
    {
        return $this->belongsTo(RentToRentBill::class, 'rent_to_rent_bill_id');
    }

    public function debt()
    {
        return $this->belongsTo(RentToRentDebt::class, 'rent_to_rent_debt_id');
    }

    public function bookingDetail()
    {
        return $this->belongsTo(BookingDetail::class);
    }

    public function allocations()
    {
        return $this->hasMany(RentToRentPaymentAllocation::class, 'rent_to_rent_bill_item_id');
    }
}
