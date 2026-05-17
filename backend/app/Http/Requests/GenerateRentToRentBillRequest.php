<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateRentToRentBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'debt_ids' => ['required', 'array', 'min:1'],
            'debt_ids.*' => ['integer', 'exists:rent_to_rent_debts,id'],
        ];
    }
}
