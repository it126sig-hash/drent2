<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseFleetCardFundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'close_note' => ['nullable', 'string'],
        ];
    }
}
