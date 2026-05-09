<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// TODO: konfirmasi strategi arsip (soft-delete vs tabel terpisah vs is_archived flag)
// Keputusan ini belum final per [2026-05-09].

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'is_active'];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
