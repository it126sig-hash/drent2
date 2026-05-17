<?php

namespace App\Policies;

use App\Models\RentToRentBill;
use App\Models\User;

class RentToRentBillPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch', 'finance'], true);
    }

    public function view(User $user, RentToRentBill $bill): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        return in_array($user->role, ['admin_branch', 'finance'], true)
            && $user->branch_id === $bill->branch_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch', 'finance'], true);
    }

    public function update(User $user, RentToRentBill $bill): bool
    {
        return $this->view($user, $bill);
    }
}
