<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentAccountTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_payment_account_id' => ['required', 'integer', 'exists:payment_accounts,id'],
            'to_payment_account_id' => ['required', 'integer', 'different:from_payment_account_id', 'exists:payment_accounts,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'transaction_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
