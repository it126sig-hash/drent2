<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => [
                'required',
                'string',
                'max:100',
                Rule::unique('cities', 'nama')
                    ->where('tenant_id', auth()->user()?->tenant_id)
                    ->whereNull('deleted_at'),
            ],
            'province_id' => ['nullable', 'exists:provinces,id'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }
}
