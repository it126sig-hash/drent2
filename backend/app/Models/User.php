<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// TODO: konfirmasi strategi arsip (soft-delete vs tabel terpisah vs is_archived flag)
// Keputusan ini belum final per [2026-05-09].

#[Fillable(['name', 'email', 'password', 'tenant_id', 'branch_id', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getRoleLabelAttribute(): string
    {
        return match($this->role) {
            'superadmin'   => 'Super Admin',
            'admin_branch' => 'Admin Branch',
            'supervisor'   => 'Supervisor',
            'finance'      => 'Finance',
            'driver_tetap' => 'Driver Tetap',
            'cs'           => 'Customer Service',
            'teknisi'      => 'Teknisi',
            default        => $this->role,
        };
    }
}
