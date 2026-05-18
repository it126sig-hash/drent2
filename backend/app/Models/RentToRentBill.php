<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RentToRentBill extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'rental_owner_id',
        'bill_number',
        'public_token',
        'status',
        'total_amount',
        'paid_amount',
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
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'paid_amount' => 'integer',
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'voided_at' => 'datetime',
        'void_requested_at' => 'datetime',
        'void_approved_at' => 'datetime',
        'void_rejected_at' => 'datetime',
    ];

    public function rentalOwner()
    {
        return $this->belongsTo(RentalOwner::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function items()
    {
        return $this->hasMany(RentToRentBillItem::class);
    }

    public function payments()
    {
        return $this->hasMany(RentToRentPayment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
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
