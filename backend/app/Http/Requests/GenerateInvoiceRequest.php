<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_ids'          => ['required', 'array', 'min:1'],
            'booking_ids.*'        => ['integer', 'exists:bookings,id'],
            'amounts'              => ['nullable', 'array'],
            'amounts.*'            => ['nullable', 'integer', 'min:1'],
            'due_date'             => ['nullable', 'date'],
            'terms_and_conditions' => ['nullable', 'string'],
            'payment_classification' => ['nullable', 'in:dp,cicilan,pelunasan'],
        ];
    }
}
