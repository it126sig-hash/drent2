<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentAccountRequest extends FormRequest
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
            'nama_bank'       => 'sometimes|required|string|max:100',
            'nomor_rekening'  => 'sometimes|required|string|max:50',
            'atas_nama'       => 'sometimes|required|string|max:150',
            'current_balance' => 'sometimes|integer',
            'is_active'       => 'sometimes|boolean',
            'branch_id'       => 'sometimes|required|exists:branches,id',
        ];
    }
}
