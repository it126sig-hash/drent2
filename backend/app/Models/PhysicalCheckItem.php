<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhysicalCheckItem extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'is_required',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
