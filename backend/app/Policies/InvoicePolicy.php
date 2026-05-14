<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch', 'finance']);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->role === 'superadmin') return true;

        return in_array($user->role, ['admin_branch', 'finance'])
            && $user->branch_id === $invoice->branch_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch', 'finance']);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }
}
