<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceBooking extends Model
{
    protected $fillable = [
        'invoice_id',
        'booking_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];
}
