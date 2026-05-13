<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingPackageItem extends Model
{
    protected $fillable = [
        'pricing_package_id',
        'cost_type_id',
        'type',
        'label',
        'amount',
        'keterangan',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'integer',
        'sort_order' => 'integer',
    ];

    public function pricingPackage()
    {
        return $this->belongsTo(PricingPackage::class);
    }

    public function costType()
    {
        return $this->belongsTo(CostType::class);
    }
}
