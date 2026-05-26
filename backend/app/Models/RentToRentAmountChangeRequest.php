<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentToRentAmountChangeRequest extends Model
{
    protected $fillable = [
        'rent_to_rent_debt_id',
        'requested_amount_override',
        'reason',
        'status',
        'requested_by',
        'approved_by',
        'rejected_by',
        'requested_at',
        'reviewed_at',
        'rejection_note',
    ];

    protected $casts = [
        'requested_amount_override' => 'integer',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function debt()
    {
        return $this->belongsTo(RentToRentDebt::class, 'rent_to_rent_debt_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
