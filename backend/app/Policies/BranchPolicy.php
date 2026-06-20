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
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'master.branch');
    }

    /**
     * Cek apakah user dapat melihat detail branch tertentu.
     * - superadmin: harus tenant yang sama
     * - role lain: butuh permission 'master.branch', dan kalau admin_branch harus branch sendiri
     */
    public function view(User $user, Branch $branch): bool
    {
        if ($user->tenant_id !== $branch->tenant_id) {
            return false;
        }

        if ($user->role === 'superadmin') {
            return true;
        }

        if (!app(\App\Services\PermissionService::class)->hasPermission($user, 'master.branch')) {
            return false;
        }

        if ($user->role === 'admin_branch') {
            return $user->branch_id === $branch->id;
        }

        return true;
    }

    /**
     * Cek apakah user dapat membuat branch baru.
     */
    public function create(User $user): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'master.branch.create');
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

        if (!app(\App\Services\PermissionService::class)->hasPermission($user, 'master.branch.update')) {
            return false;
        }

        if ($user->role === 'admin_branch') {
            return $user->branch_id === $branch->id;
        }

        return true;
    }

    /**
     * Cek apakah user dapat hapus branch (di tenant yang sama).
     */
    public function delete(User $user, Branch $branch): bool
    {
        if ($user->tenant_id !== $branch->tenant_id) {
            return false;
        }

        if ($user->role === 'superadmin') {
            return true;
        }

        return app(\App\Services\PermissionService::class)->hasPermission($user, 'master.branch.delete');
    }
}
