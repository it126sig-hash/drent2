<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetCardFundItem extends Model
{
    protected $fillable = [
        'fleet_card_fund_id',
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
        return $this->belongsTo(FleetCardFund::class, 'fleet_card_fund_id');
    }

    public function costType()
    {
        return $this->belongsTo(CostType::class);
    }
}
