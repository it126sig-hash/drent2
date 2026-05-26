<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Services\PermissionService;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(PermissionService::class)->hasPermission($user, 'finance.receivable');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->role === 'superadmin') return true;

        return app(PermissionService::class)->hasPermission($user, 'finance.receivable')
            && $user->branch_id === $invoice->branch_id;
    }

    public function create(User $user): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(PermissionService::class)->hasPermission($user, 'finance.receivable');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }
}
