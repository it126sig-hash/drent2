<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentAccountAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_account_id' => ['required', 'integer', 'exists:payment_accounts,id'],
            'current_balance' => ['required', 'integer'],
            'transaction_at' => ['nullable', 'date'],
            'description' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
