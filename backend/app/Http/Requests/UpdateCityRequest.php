<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $city = $this->route('city');

        return [
            'nama' => [
                'required',
                'string',
                'max:100',
                Rule::unique('cities', 'nama')
                    ->ignore($city?->id)
                    ->where('tenant_id', auth()->user()?->tenant_id)
                    ->whereNull('deleted_at'),
            ],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }
}
