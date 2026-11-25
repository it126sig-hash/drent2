<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFleetCardFundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fleet_card_id' => ['required', 'exists:fleet_cards,id'],
            'driver_id' => ['required', 'exists:drivers,id'],
            'payment_account_id' => [
                function ($attribute, $value, $fail) {
                    if ((int) $this->input('amount', 0) > 0 && ! $value) {
                        $fail('Rekening sumber wajib diisi jika ada top-up.');
                    }
                },
                'nullable',
                'exists:payment_accounts,id',
            ],
            'amount' => ['required', 'integer', 'min:0'],
            'paid_at' => ['nullable', 'date'],
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
