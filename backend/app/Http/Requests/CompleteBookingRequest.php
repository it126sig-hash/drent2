<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'skip_inspection' => ['sometimes', 'boolean'],
            'returned_at' => ['nullable', 'date'],
        ];
    }
}
