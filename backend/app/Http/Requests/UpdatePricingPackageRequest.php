<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePricingPackageRequest extends FormRequest
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
            'nama_paket'  => 'sometimes|required|string|max:150',
            'cost_type_id' => [
                'nullable',
                Rule::exists('cost_types', 'id')->where('tenant_id', auth()->user()->tenant_id),
            ],
            'harga'       => 'sometimes|required|integer|min:0',
            'keterangan'  => 'nullable|string',
            'is_active'   => 'sometimes|boolean',
            'branch_id'   => 'sometimes|required|exists:branches,id',
            'items'                 => ['nullable', 'array'],
            'items.*.cost_type_id'  => [
                'nullable',
                Rule::exists('cost_types', 'id')->where('tenant_id', auth()->user()->tenant_id),
            ],
            'items.*.type'          => ['nullable', 'string', 'in:biaya,diskon'],
            'items.*.label'         => ['required_with:items', 'string', 'max:255'],
            'items.*.amount'        => ['required_with:items', 'integer', 'min:0'],
            'items.*.keterangan'    => ['nullable', 'string'],
        ];
    }
}
