<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCostTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorized by Policy in Controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'nama'                => 'required|string|max:100',
            'kode'                => 'required|string|max:50|unique:cost_types,kode,NULL,id,tenant_id,' . auth()->user()->tenant_id,
            'require_description' => 'sometimes|boolean',
            'is_active'           => 'sometimes|boolean',
            'sort_order'          => 'sometimes|integer|min:0',
        ];
    }
}
