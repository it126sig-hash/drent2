<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAccountTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'payment_account_id' => $this->payment_account_id,
            'related_payment_account_id' => $this->related_payment_account_id,
            'finance_category_id' => $this->finance_category_id,
            'type' => $this->type,
            'transfer_group_id' => $this->transfer_group_id,
            'amount' => (int) $this->amount,
            'signed_amount' => (int) $this->signed_amount,
            'balance_before' => (int) $this->balance_before,
            'balance_after' => (int) $this->balance_after,
            'transaction_at' => $this->transaction_at?->toISOString(),
            'description' => $this->description,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'payment_account' => $this->whenLoaded('paymentAccount', fn () => $this->accountPayload($this->paymentAccount)),
            'related_payment_account' => $this->whenLoaded('relatedPaymentAccount', fn () => $this->relatedPaymentAccount ? $this->accountPayload($this->relatedPaymentAccount) : null),
            'finance_category' => $this->whenLoaded('financeCategory', fn () => $this->financeCategory ? [
                'id' => $this->financeCategory->id,
                'name' => $this->financeCategory->name,
                'type' => $this->financeCategory->type,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
        ];
    }

    private function accountPayload($account): array
    {
        return [
            'id' => $account->id,
            'nama_bank' => $account->nama_bank,
            'nomor_rekening' => $account->nomor_rekening,
            'atas_nama' => $account->atas_nama,
            'current_balance' => (int) $account->current_balance,
        ];
    }
}
