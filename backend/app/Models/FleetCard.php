<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetCard extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'code',
        'label',
        'saldo',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'saldo' => 'integer',
    ];

    public function funds()
    {
        return $this->hasMany(FleetCardFund::class);
    }

    public function expenses()
    {
        return $this->hasMany(FleetCardExpense::class);
    }

    public function ledger()
    {
        return $this->hasMany(FleetCardBalanceLedger::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activeFund()
    {
        return $this->hasOne(FleetCardFund::class)
            ->whereIn('status', ['pending_driver_acceptance', 'accepted'])
            ->latest();
    }
}
