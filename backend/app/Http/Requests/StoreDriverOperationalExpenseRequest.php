<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDriverOperationalExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cost_type_id' => ['nullable', 'exists:cost_types,id'],
            'type' => ['required', 'in:expense,return'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'min:3'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'payment_account_id' => ['nullable', 'exists:payment_accounts,id'],
        ];
    }
}
