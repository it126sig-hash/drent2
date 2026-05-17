<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = User::query()
            ->with(['branch', 'driver'])
            ->where('tenant_id', Auth::user()->tenant_id);

        // Branch scope (Global Rule #8)
        if (isset($filters['branch_id']) && $filters['branch_id'] !== 'all') {
            $query->where('branch_id', $filters['branch_id']);
        } else if (Auth::user()->role !== 'superadmin') {
            $query->where('branch_id', Auth::user()->branch_id);
        }

        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): User
    {
        $driverId = $data['driver_id'] ?? null;
        unset($data['driver_id']);

        $data['tenant_id'] = Auth::user()->tenant_id;
        $data['password'] = Hash::make($data['password']);
        
        return DB::transaction(function () use ($data, $driverId) {
            $user = User::create($data);
            $this->syncDriver($user, $data['role'], $driverId);

            return $user->load(['branch', 'driver']);
        });
    }

    public function update(User $user, array $data): User
    {
        $driverId = $data['driver_id'] ?? null;
        $hasDriver = array_key_exists('driver_id', $data);
        unset($data['driver_id']);

        // Don't update password in general update
        unset($data['password']);

        return DB::transaction(function () use ($user, $data, $driverId, $hasDriver) {
            $role = $data['role'] ?? $user->role;

            $user->update($data);

            if ($role !== 'driver_tetap') {
                Driver::query()
                    ->where('user_id', $user->id)
                    ->update(['user_id' => null]);
            } elseif ($hasDriver) {
                $this->syncDriver($user, $role, $driverId);
            }

            return $user->load(['branch', 'driver']);
        });
    }

    public function delete(User $user): bool
    {
        return $user->delete();
    }

    public function resetPassword(User $user, string $newPassword): User
    {
        $user->update(['password' => Hash::make($newPassword)]);
        return $user;
    }

    private function syncDriver(User $user, string $role, ?int $driverId): void
    {
        if ($role !== 'driver_tetap' || ! $driverId) {
            return;
        }

        Driver::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $driverId)
            ->update(['user_id' => null]);

        Driver::query()
            ->whereKey($driverId)
            ->update(['user_id' => $user->id]);
    }
}
