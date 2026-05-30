<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceHistory extends Model
{
    protected $fillable = [
        'invoice_id',
        'event_type',
        'description',
        'amount_before',
        'amount_after',
        'payment_amount',
        'created_by',
    ];

    protected $casts = [
        'amount_before' => 'integer',
        'amount_after' => 'integer',
        'payment_amount' => 'integer',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
