<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentAccountRequest extends FormRequest
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
            'nama_bank'       => 'required|string|max:100',
            'nomor_rekening'  => 'required|string|max:50',
            'atas_nama'       => 'required|string|max:150',
            'is_active'       => 'sometimes|boolean',
            'branch_id'       => 'nullable|exists:branches,id',
        ];
    }
}
