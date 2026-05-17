<?php

namespace App\Policies;

use App\Models\DriverOperationalFund;
use App\Models\User;

class DriverOperationalFundPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch', 'finance', 'driver_tetap'], true);
    }

    public function view(User $user, DriverOperationalFund $fund): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        if ($user->role === 'driver_tetap') {
            return $fund->driver?->user_id === $user->id;
        }

        return in_array($user->role, ['admin_branch', 'finance'], true)
            && $user->branch_id === $fund->branch_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch', 'finance'], true);
    }

    public function accept(User $user, DriverOperationalFund $fund): bool
    {
        return $user->role === 'driver_tetap'
            && $fund->driver?->user_id === $user->id;
    }

    public function manageExpense(User $user, DriverOperationalFund $fund): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        if ($user->role === 'driver_tetap') {
            return $fund->driver?->user_id === $user->id;
        }

        return in_array($user->role, ['admin_branch', 'finance'], true)
            && $user->branch_id === $fund->branch_id;
    }

    public function review(User $user, DriverOperationalFund $fund): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        return in_array($user->role, ['admin_branch', 'finance'], true)
            && $user->branch_id === $fund->branch_id;
    }
}
