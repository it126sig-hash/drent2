<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDriverOperationalFundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_detail_id' => ['nullable', 'exists:booking_details,id'],
            'driver_id' => ['required', 'exists:drivers,id'],
            'payment_account_id' => ['required', 'exists:payment_accounts,id'],
            'fund_type' => ['nullable', 'in:operational,salary'],
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'recipient_destination' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.cost_type_id' => ['nullable', 'exists:cost_types,id'],
            'items.*.label' => ['required', 'string', 'max:150'],
            'items.*.planned_amount' => ['required', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
