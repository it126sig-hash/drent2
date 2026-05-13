<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch', 'cs', 'finance']);
    }

    public function view(User $user, Booking $booking): bool
    {
        if ($user->role === 'superadmin') return true;
        return $user->branch_id === $booking->branch_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['cs', 'admin_branch', 'superadmin']);
    }

    public function updateStatus(User $user, Booking $booking): bool
    {
        if ($user->role === 'superadmin') return true;
        return in_array($user->role, ['admin_branch', 'cs'])
            && $user->branch_id === $booking->branch_id;
    }

    public function update(User $user, Booking $booking): bool
    {
        if ($user->role === 'superadmin') return true;
        return in_array($user->role, ['admin_branch', 'cs'])
            && $user->branch_id === $booking->branch_id;
    }

    public function managePayments(User $user, Booking $booking): bool
    {
        if ($user->role === 'superadmin') return true;
        return in_array($user->role, ['admin_branch', 'cs', 'finance'])
            && $user->branch_id === $booking->branch_id;
    }

    public function manageRefunds(User $user, Booking $booking): bool
    {
        if ($user->role === 'superadmin') return true;
        return in_array($user->role, ['admin_branch', 'finance'])
            && $user->branch_id === $booking->branch_id;
    }

    public function checkout(User $user, Booking $booking): bool
    {
        if ($user->role === 'superadmin') return true;
        return in_array($user->role, ['admin_branch', 'cs'])
            && $user->branch_id === $booking->branch_id;
    }

    public function complete(User $user, Booking $booking): bool
    {
        if ($user->role === 'superadmin') return true;
        return in_array($user->role, ['admin_branch', 'cs'])
            && $user->branch_id === $booking->branch_id;
    }
}
