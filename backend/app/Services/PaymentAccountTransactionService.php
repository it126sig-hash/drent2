<?php

namespace App\Services;

use App\Models\FinanceCategory;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentAccountTransactionService
{
    public function getAll(array $filters = [])
    {
        $user = Auth::user();

        $query = PaymentAccountTransaction::query()
            ->with(['paymentAccount', 'relatedPaymentAccount', 'financeCategory', 'creator'])
            ->where('tenant_id', $user->tenant_id)
            ->when($this->branchId($filters, $user), fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['payment_account_id'] ?? null, fn ($query, $accountId) => $query->where('payment_account_id', $accountId))
            ->when($filters['finance_category_id'] ?? null, fn ($query, $categoryId) => $query->where('finance_category_id', $categoryId))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->where('transaction_at', '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->where('transaction_at', '<=', Carbon::parse($date)->endOfDay()))
            ->latest('transaction_at')
            ->latest('id');

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function transfer(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();
            $from = $this->lockAccount((int) $data['from_payment_account_id'], $user);
            $to = $this->lockAccount((int) $data['to_payment_account_id'], $user);

            if ($from->id === $to->id) {
                throw ValidationException::withMessages(['to_payment_account_id' => ['Rekening tujuan harus berbeda.']]);
            }

            if ($from->branch_id !== $to->branch_id) {
                throw ValidationException::withMessages(['to_payment_account_id' => ['Transfer hanya bisa dilakukan antar rekening dalam branch yang sama.']]);
            }

            $amount = (int) $data['amount'];
            $transferGroupId = (string) Str::uuid();
            $transactionAt = $this->transactionAt($data);
            $description = $data['description'] ?? null;

            $out = $this->applyDelta($from, -$amount, [
                'related_payment_account_id' => $to->id,
                'type' => 'transfer_out',
                'transfer_group_id' => $transferGroupId,
                'amount' => $amount,
                'transaction_at' => $transactionAt,
                'description' => $description,
                'created_by' => $user->id,
            ]);

            $in = $this->applyDelta($to, $amount, [
                'related_payment_account_id' => $from->id,
                'type' => 'transfer_in',
                'transfer_group_id' => $transferGroupId,
                'amount' => $amount,
                'transaction_at' => $transactionAt,
                'description' => $description,
                'created_by' => $user->id,
            ]);

            return [$out->fresh($this->relations()), $in->fresh($this->relations())];
        });
    }

    public function other(array $data): PaymentAccountTransaction
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();
            $account = $this->lockAccount((int) $data['payment_account_id'], $user);
            $category = $this->category((int) $data['finance_category_id'], $user, $account->branch_id);
            $type = $data['type'] === 'income' ? 'other_income' : 'other_expense';

            if ($category->type !== $data['type']) {
                throw ValidationException::withMessages(['finance_category_id' => ['Kategori tidak sesuai dengan tipe transaksi.']]);
            }

            $amount = (int) $data['amount'];
            $transaction = $this->applyDelta($account, $type === 'other_income' ? $amount : -$amount, [
                'finance_category_id' => $category->id,
                'type' => $type,
                'amount' => $amount,
                'transaction_at' => $this->transactionAt($data),
                'description' => $data['description'] ?? null,
                'created_by' => $user->id,
            ]);

            return $transaction->fresh($this->relations());
        });
    }

    public function adjustment(array $data): PaymentAccountTransaction
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();
            $account = $this->lockAccount((int) $data['payment_account_id'], $user);
            $newBalance = (int) $data['current_balance'];
            $delta = $newBalance - (int) $account->current_balance;

            if ($delta === 0) {
                throw ValidationException::withMessages(['current_balance' => ['Saldo baru sama dengan saldo saat ini.']]);
            }

            $transaction = $this->applyDelta($account, $delta, [
                'type' => 'balance_adjustment',
                'amount' => abs($delta),
                'transaction_at' => $this->transactionAt($data),
                'description' => $data['description'],
                'created_by' => $user->id,
            ]);

            return $transaction->fresh($this->relations());
        });
    }

    public function applyDelta(PaymentAccount $account, int $delta, array $payload): PaymentAccountTransaction
    {
        $before = (int) $account->current_balance;
        $after = $before + $delta;

        $account->update(['current_balance' => $after]);

        return PaymentAccountTransaction::create(array_merge($payload, [
            'tenant_id' => $account->tenant_id,
            'branch_id' => $account->branch_id,
            'payment_account_id' => $account->id,
            'signed_amount' => $delta,
            'balance_before' => $before,
            'balance_after' => $after,
        ]));
    }

    private function lockAccount(int $id, User $user): PaymentAccount
    {
        return PaymentAccount::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->when($user->role !== 'superadmin', fn (Builder $query) => $query->where('branch_id', $user->branch_id))
            ->lockForUpdate()
            ->findOrFail($id);
    }

    private function category(int $id, User $user, int $branchId): FinanceCategory
    {
        return FinanceCategory::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->findOrFail($id);
    }

    private function branchId(array $filters, User $user): ?int
    {
        if ($user->role !== 'superadmin') {
            return $user->branch_id;
        }

        return isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
    }

    private function transactionAt(array $data): Carbon
    {
        return \App\Helpers\DateHelper::parseDateWithCurrentTime($data['transaction_at'] ?? null);
    }

    private function relations(): array
    {
        return ['paymentAccount', 'relatedPaymentAccount', 'financeCategory', 'creator'];
    }
}
