<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFleetCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'label' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
