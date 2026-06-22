<?php

namespace App\Policies;

use App\Models\FleetCardFund;
use App\Models\User;
use App\Services\PermissionService;

class FleetCardFundPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->role === 'superadmin' || $user->role === 'driver_tetap') {
            return true;
        }

        return app(PermissionService::class)->hasPermission($user, 'finance.fleet_card');
    }

    public function view(User $user, FleetCardFund $fund): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        if ($user->role === 'driver_tetap') {
            return $fund->driver?->user_id === $user->id;
        }

        return app(PermissionService::class)->hasPermission($user, 'finance.fleet_card')
            && $user->branch_id === $fund->branch_id;
    }

    public function create(User $user): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        return app(PermissionService::class)->hasPermission($user, 'finance.fleet_card.create');
    }

    public function accept(User $user, FleetCardFund $fund): bool
    {
        return $user->role === 'driver_tetap'
            && $fund->driver?->user_id === $user->id;
    }

    public function manageExpense(User $user, ?FleetCardFund $fund = null): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        if ($fund) {
            if ($user->role === 'driver_tetap') {
                return $fund->driver?->user_id === $user->id;
            }

            return app(PermissionService::class)->hasPermission($user, 'finance.fleet_card.create')
                && $user->branch_id === $fund->branch_id;
        }

        return app(PermissionService::class)->hasPermission($user, 'finance.fleet_card.create');
    }

    public function review(User $user, ?FleetCardFund $fund = null): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        if ($fund) {
            return app(PermissionService::class)->hasPermission($user, 'finance.fleet_card.update')
                && $user->branch_id === $fund->branch_id;
        }

        return app(PermissionService::class)->hasPermission($user, 'finance.fleet_card.update');
    }
}
