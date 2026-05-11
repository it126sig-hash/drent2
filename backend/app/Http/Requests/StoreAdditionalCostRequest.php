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
            'type' => 'required|string|in:driver,bbm,tol,parkir,lainnya,diskon',
            'label' => 'required|string',
            'amount' => 'required|integer',
            'is_discount' => 'boolean',
        ];
    }
}
