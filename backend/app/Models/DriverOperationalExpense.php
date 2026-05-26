<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DriverOperationalExpense extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'driver_operational_fund_id',
        'booking_id',
        'booking_detail_id',
        'driver_id',
        'cost_type_id',
        'payment_account_id',
        'type',
        'amount',
        'description',
        'photo_path',
        'status',
        'source',
        'submitted_by',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'void_reason',
        'void_requested_by',
        'void_requested_at',
        'void_approved_by',
        'void_approved_at',
        'void_rejected_by',
        'void_rejected_at',
        'void_rejection_note',
    ];

    protected $casts = [
        'amount' => 'integer',
        'reviewed_at' => 'datetime',
        'void_requested_at' => 'datetime',
        'void_approved_at' => 'datetime',
        'void_rejected_at' => 'datetime',
    ];

    public function fund()
    {
        return $this->belongsTo(DriverOperationalFund::class, 'driver_operational_fund_id');
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingDetail()
    {
        return $this->belongsTo(BookingDetail::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function costType()
    {
        return $this->belongsTo(CostType::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
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

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::url($this->photo_path) : null;
    }
}
