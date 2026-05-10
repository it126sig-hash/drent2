<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingCost extends Model
{
    protected $fillable = [
        'booking_detail_id',
        'type',
        'label',
        'amount',
    ];

    public function bookingDetail()
    {
        return $this->belongsTo(BookingDetail::class);
    }
}
