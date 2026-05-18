<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverOperationalFundItem extends Model
{
    protected $fillable = [
        'driver_operational_fund_id',
        'cost_type_id',
        'label',
        'planned_amount',
        'notes',
    ];

    protected $casts = [
        'planned_amount' => 'integer',
    ];

    public function fund()
    {
        return $this->belongsTo(DriverOperationalFund::class, 'driver_operational_fund_id');
    }

    public function costType()
    {
        return $this->belongsTo(CostType::class);
    }
}
