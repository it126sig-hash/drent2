<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PricingPackage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'cost_type_id',
        'nama_paket',
        'kota_asal',
        'kota_tujuan',
        'harga',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'harga' => 'integer',
        'is_active' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function costType()
    {
        return $this->belongsTo(CostType::class);
    }

    public function items()
    {
        return $this->hasMany(PricingPackageItem::class)->orderBy('sort_order');
    }
}
