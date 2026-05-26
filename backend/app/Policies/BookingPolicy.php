<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'booking.view');
    }

    public function view(User $user, Booking $booking): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'booking.view')
            && $user->branch_id === $booking->branch_id;
    }

    public function create(User $user): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'booking.create');
    }

    public function updateStatus(User $user, Booking $booking): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'booking.handle')
            && $user->branch_id === $booking->branch_id;
    }

    public function update(User $user, Booking $booking): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'booking.handle')
            && $user->branch_id === $booking->branch_id;
    }

    public function managePayments(User $user, Booking $booking): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'booking.handle')
            && $user->branch_id === $booking->branch_id;
    }

    public function approvePaymentVoid(User $user, Booking $booking): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'booking.supervisor_request')
            && $user->branch_id === $booking->branch_id;
    }

    public function requestRentalUnitReturn(User $user, Booking $booking): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'booking.handle')
            && $user->branch_id === $booking->branch_id;
    }

    public function approveRentalUnitReturn(User $user, Booking $booking): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'booking.supervisor_request')
            && $user->branch_id === $booking->branch_id;
    }

    public function manageRefunds(User $user, Booking $booking): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'booking.handle')
            && $user->branch_id === $booking->branch_id;
    }

    public function checkout(User $user, Booking $booking): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'booking.handle')
            && $user->branch_id === $booking->branch_id;
    }

    public function complete(User $user, Booking $booking): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'booking.handle')
            && $user->branch_id === $booking->branch_id;
    }
}
