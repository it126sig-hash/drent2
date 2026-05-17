<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RentToRentDebt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'rental_owner_id',
        'booking_id',
        'booking_detail_id',
        'amount_override',
        'status',
    ];

    protected $casts = [
        'amount_override' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function rentalOwner()
    {
        return $this->belongsTo(RentalOwner::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingDetail()
    {
        return $this->belongsTo(BookingDetail::class);
    }

    public function billItems()
    {
        return $this->hasMany(RentToRentBillItem::class);
    }

    public function paymentAllocations()
    {
        return $this->hasMany(RentToRentPaymentAllocation::class);
    }
}
