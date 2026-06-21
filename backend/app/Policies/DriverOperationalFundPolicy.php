<?php

namespace App\Policies;

use App\Models\DriverOperationalFund;
use App\Models\User;
use App\Services\PermissionService;

class DriverOperationalFundPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->role === 'superadmin' || $user->role === 'driver_tetap') {
            return true;
        }

        return app(PermissionService::class)->hasPermission($user, 'finance.operational_cost');
    }

    public function view(User $user, DriverOperationalFund $fund): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        if ($user->role === 'driver_tetap') {
            return $fund->driver?->user_id === $user->id;
        }

        return app(PermissionService::class)->hasPermission($user, 'finance.operational_cost')
            && $user->branch_id === $fund->branch_id;
    }

    public function create(User $user): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        return app(PermissionService::class)->hasPermission($user, 'finance.operational_cost.create');
    }

    public function accept(User $user, DriverOperationalFund $fund): bool
    {
        return $user->role === 'driver_tetap'
            && $fund->driver?->user_id === $user->id;
    }

    public function manageExpense(User $user, ?DriverOperationalFund $fund = null): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        if ($fund) {
            if ($user->role === 'driver_tetap') {
                return $fund->driver?->user_id === $user->id;
            }

            return app(PermissionService::class)->hasPermission($user, 'finance.operational_cost.create')
                && $user->branch_id === $fund->branch_id;
        }

        return app(PermissionService::class)->hasPermission($user, 'finance.operational_cost.create');
    }

    public function review(User $user, ?DriverOperationalFund $fund = null): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        if ($fund) {
            return app(PermissionService::class)->hasPermission($user, 'finance.operational_cost.update')
                && $user->branch_id === $fund->branch_id;
        }

        return app(PermissionService::class)->hasPermission($user, 'finance.operational_cost.update');
    }
}
