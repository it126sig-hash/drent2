<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverBalanceLedger extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'driver_id',
        'booking_id',
        'driver_operational_fund_id',
        'driver_operational_expense_id',
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
}
