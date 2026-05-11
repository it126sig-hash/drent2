<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin', 'cs', 'finance', 'surveyor']);
    }

    public function view(User $user, Member $member): bool
    {
        return in_array($user->role, ['superadmin', 'admin', 'cs', 'finance', 'surveyor']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin', 'cs', 'surveyor']);
    }

    public function update(User $user, Member $member): bool
    {
        return in_array($user->role, ['superadmin', 'admin', 'cs', 'surveyor']);
    }

    public function delete(User $user, Member $member): bool
    {
        return in_array($user->role, ['superadmin', 'admin']);
    }

    public function activate(User $user, Member $member): bool
    {
        return in_array($user->role, ['superadmin', 'admin']);
    }

    public function viewDocument(User $user, Member $member): bool
    {
        return in_array($user->role, ['superadmin', 'admin', 'cs', 'finance', 'surveyor']);
    }
}
