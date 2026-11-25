<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'invoice_number',
        'public_token',
        'status',
        'merged_into_id',
        'total_amount',
        'is_manual_amount',
        'payment_classification',
        'paid_amount',
        'items_snapshot',
        'due_date',
        'terms_and_conditions',
        'generated_at',
        'sent_at',
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
        'sent_by',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'is_manual_amount' => 'boolean',
        'paid_amount' => 'integer',
        'items_snapshot' => 'array',
        'due_date' => 'datetime',
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'voided_at' => 'datetime',
        'void_requested_at' => 'datetime',
        'void_approved_at' => 'datetime',
        'void_rejected_at' => 'datetime',
    ];

    // TODO: konfirmasi strategi arsip (soft-delete vs tabel terpisah vs is_archived flag)

    public function bookings()
    {
        return $this->belongsToMany(Booking::class, 'invoice_bookings')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function histories()
    {
        return $this->hasMany(InvoiceHistory::class)->orderBy('created_at');
    }

    public function mergedInto()
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function childInvoices()
    {
        return $this->belongsToMany(self::class, 'invoice_invoices', 'parent_invoice_id', 'child_invoice_id')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function childInvoicePivots()
    {
        return $this->hasMany(InvoiceInvoice::class, 'parent_invoice_id')->orderBy('id');
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
}
