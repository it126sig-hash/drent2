<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetCardBalanceLedger extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'fleet_card_id',
        'booking_id',
        'fleet_card_fund_id',
        'fleet_card_expense_id',
        'direction',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
    ];

    public function fleetCard()
    {
        return $this->belongsTo(FleetCard::class);
    }

    public function fund()
    {
        return $this->belongsTo(FleetCardFund::class, 'fleet_card_fund_id');
    }

    public function expense()
    {
        return $this->belongsTo(FleetCardExpense::class, 'fleet_card_expense_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
