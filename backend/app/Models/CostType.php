<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostType extends Model
{
    protected $fillable = [
        'tenant_id',
        'nama',
        'kode',
        'require_description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'require_description' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
