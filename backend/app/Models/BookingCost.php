<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingCost extends Model
{
    protected $fillable = [
        'booking_detail_id',
        'cost_type_id',
        'type',
        'label',
        'amount',
        'keterangan',
    ];

    public function bookingDetail()
    {
        return $this->belongsTo(BookingDetail::class);
    }

    public function costType()
    {
        return $this->belongsTo(CostType::class);
    }
}
