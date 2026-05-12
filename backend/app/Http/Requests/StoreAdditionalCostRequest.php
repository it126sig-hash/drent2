<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdditionalCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cost_type_id' => 'required|exists:cost_types,id',
            'type' => 'required|string|in:biaya,diskon',
            'label' => 'nullable|string|max:255',
            'amount' => 'required|integer|min:0',
            'keterangan' => 'nullable|string',
            'is_discount' => 'boolean',
        ];
    }
}
