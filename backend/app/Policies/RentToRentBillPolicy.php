<?php

namespace App\Policies;

use App\Models\RentToRentBill;
use App\Models\User;
use App\Services\PermissionService;

class RentToRentBillPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(PermissionService::class)->hasPermission($user, 'finance.rent_to_rent');
    }

    public function view(User $user, RentToRentBill $bill): bool
    {
        if ($user->role === 'superadmin') return true;

        return app(PermissionService::class)->hasPermission($user, 'finance.rent_to_rent')
            && $user->branch_id === $bill->branch_id;
    }

    public function create(User $user): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(PermissionService::class)->hasPermission($user, 'finance.rent_to_rent');
    }

    public function update(User $user, RentToRentBill $bill): bool
    {
        return $this->view($user, $bill);
    }
}
