<?php

namespace App\Policies;

use App\Models\FinanceCategory;
use App\Models\User;

class FinanceCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch', 'finance']);
    }

    public function view(User $user, FinanceCategory $financeCategory): bool
    {
        if ($user->role === 'superadmin') {
            return $user->tenant_id === $financeCategory->tenant_id;
        }

        return $user->tenant_id === $financeCategory->tenant_id
            && $user->branch_id === $financeCategory->branch_id
            && in_array($user->role, ['admin_branch', 'finance']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch', 'finance']);
    }

    public function update(User $user, FinanceCategory $financeCategory): bool
    {
        return $this->view($user, $financeCategory);
    }

    public function delete(User $user, FinanceCategory $financeCategory): bool
    {
        return $this->view($user, $financeCategory);
    }
}
