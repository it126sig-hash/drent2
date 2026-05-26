<?php

namespace App\Policies;

use App\Models\PaymentAccount;
use App\Models\User;

class PaymentAccountPolicy
{
    private $permission = 'master.payment_account';

    public function viewAny(User $user): bool
    {
        // if ($user->role === 'superadmin') return true;
        // return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission);
        return true;
    }

    public function view(User $user, PaymentAccount $paymentAccount): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->branch_id === $paymentAccount->branch_id;
    }

    public function create(User $user): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission);
    }

    public function update(User $user, PaymentAccount $paymentAccount): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->branch_id === $paymentAccount->branch_id;
    }

    public function delete(User $user, PaymentAccount $paymentAccount): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->branch_id === $paymentAccount->branch_id;
    }
}
