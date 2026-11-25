<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceInvoice extends Model
{
    protected $fillable = [
        'parent_invoice_id',
        'child_invoice_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function parentInvoice()
    {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id');
    }

    public function childInvoice()
    {
        return $this->belongsTo(Invoice::class, 'child_invoice_id');
    }
}
