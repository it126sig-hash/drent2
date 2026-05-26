<?php

namespace App\Policies;

use App\Models\PaymentAccountTransaction;
use App\Models\User;
use App\Services\PermissionService;

class PaymentAccountTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->role === 'superadmin') return true;
        // Also used by MonthlyFinanceReportController — finance.account_mutation
        // covers both account mutations view AND the monthly finance report.
        return app(PermissionService::class)->hasPermission($user, 'finance.account_mutation');
    }

    public function view(User $user, PaymentAccountTransaction $transaction): bool
    {
        if ($user->role === 'superadmin') {
            return $user->tenant_id === $transaction->tenant_id;
        }

        return app(PermissionService::class)->hasPermission($user, 'finance.account_mutation')
            && $user->tenant_id === $transaction->tenant_id
            && $user->branch_id === $transaction->branch_id;
    }

    public function create(User $user): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(PermissionService::class)->hasPermission($user, 'finance.account_mutation');
    }
}
