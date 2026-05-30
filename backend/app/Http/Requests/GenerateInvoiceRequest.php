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
            'due_date'             => ['nullable', 'date'],
            'terms_and_conditions' => ['nullable', 'string'],
        ];
    }
}
