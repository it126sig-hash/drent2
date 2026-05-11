<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePricingPackageRequest extends FormRequest
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
            'nama_paket'  => 'required|string|max:150',
            'harga'       => 'required|integer|min:0',
            'keterangan'  => 'nullable|string',
            'is_active'   => 'sometimes|boolean',
            'branch_id'   => 'nullable|exists:branches,id',
        ];
    }
}
