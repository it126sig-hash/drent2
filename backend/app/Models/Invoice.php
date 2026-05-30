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
        'total_amount',
        'paid_amount',
        'due_date',
        'terms_and_conditions',
        'generated_at',
        'sent_at',
        'voided_at',
        'created_by',
        'sent_by',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'paid_amount' => 'integer',
        'due_date' => 'datetime',
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'voided_at' => 'datetime',
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
}
