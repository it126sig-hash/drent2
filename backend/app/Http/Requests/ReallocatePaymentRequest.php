<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReallocatePaymentRequest extends FormRequest
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
            'target_booking_id'  => 'required|exists:bookings,id',
            'payment_account_id' => 'required|exists:payment_accounts,id',
            'amount'             => 'required|integer|min:1',
            'payment_type'       => 'required|in:dp,cicilan,pelunasan',
            'catatan'            => 'nullable|string',
        ];
    }
}
