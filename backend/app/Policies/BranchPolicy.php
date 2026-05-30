<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    /**
     * Cek apakah user dapat melihat list branch.
     * - superadmin: ya, semua branch di tenantnya
     * - admin_branch: ya, tapi service akan memfilter ke branch sendiri saja
     * - role lain: tidak
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch'], true);
    }

    /**
     * Cek apakah user dapat melihat detail branch tertentu.
     * - superadmin: harus tenant yang sama
     * - admin_branch: harus branch sendiri
     */
    public function view(User $user, Branch $branch): bool
    {
        if ($user->tenant_id !== $branch->tenant_id) {
            return false;
        }

        if ($user->role === 'superadmin') {
            return true;
        }

        if ($user->role === 'admin_branch') {
            return $user->branch_id === $branch->id;
        }

        return false;
    }

    /**
     * Hanya superadmin yang dapat membuat branch baru.
     */
    public function create(User $user): bool
    {
        return $user->role === 'superadmin';
    }

    /**
     * Cek apakah user dapat update branch.
     * - superadmin: branch apa saja di tenantnya
     * - admin_branch: hanya branch sendiri
     */
    public function update(User $user, Branch $branch): bool
    {
        if ($user->tenant_id !== $branch->tenant_id) {
            return false;
        }

        if ($user->role === 'superadmin') {
            return true;
        }

        if ($user->role === 'admin_branch') {
            return $user->branch_id === $branch->id;
        }

        return false;
    }

    /**
     * Hanya superadmin yang dapat hapus branch (di tenant yang sama).
     */
    public function delete(User $user, Branch $branch): bool
    {
        return $user->role === 'superadmin'
            && $user->tenant_id === $branch->tenant_id;
    }
}
