<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_account_id' => ['required', 'integer', 'exists:payment_accounts,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
