<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:tenants,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'rental_owner_id' => 'nullable|integer|exists:rental_owners,id',
            'tipe' => 'required|string|max:100',
            'merk' => 'required|string|max:100',
            'tahun' => 'required|integer|min:2000|max:2099',
            'no_polisi' => [
                'required', 
                'string', 
                'max:20',
                // Unik per tenant, abaikan ID unit saat ini
                Rule::unique('units')->where(function ($query) {
                    return $query->where('tenant_id', $this->tenant_id);
                })->ignore($this->route('unit')),
            ],
            'harga_1_hari' => 'required|integer|min:0',
            'harga_1_minggu' => 'required|integer|min:0',
            'harga_1_bulan' => 'required|integer|min:0',
            'modal_1_hari' => 'required|integer|min:0',
            'modal_1_minggu' => 'required|integer|min:0',
            'modal_1_bulan' => 'required|integer|min:0',
            'status' => 'required|in:Aktif,Tidak Aktif,Dalam Servis',
            'catatan' => 'nullable|string',
        ];
    }
}
