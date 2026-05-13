<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string|in:driver,bbm,tol,parkir,lainnya',
            'label' => 'required|string',
            'amount' => 'required|integer|min:0',
        ];
    }
}
