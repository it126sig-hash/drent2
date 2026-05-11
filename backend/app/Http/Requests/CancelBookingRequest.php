<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refund_amount'         => ['nullable', 'integer', 'min:0'],
            'refund_keterangan'     => ['nullable', 'string', 'max:500'],
            'payment_account_id'    => ['nullable', 'exists:payment_accounts,id'],
        ];
    }
}
