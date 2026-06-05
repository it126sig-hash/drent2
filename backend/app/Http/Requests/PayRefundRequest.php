<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_account_id' => ['required', 'integer', 'exists:payment_accounts,id'],
        ];
    }
}
