<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRefundRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorized by Policy in Controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'payment_account_id' => 'required|exists:payment_accounts,id',
            'amount'             => 'required|integer|min:1',
            'keterangan'         => 'nullable|string',
            'refunded_at'        => 'nullable|date',
        ];
    }
}
