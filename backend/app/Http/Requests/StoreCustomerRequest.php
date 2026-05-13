<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama'             => 'required|string|max:150',
            'kontak_1'         => 'required|string|max:20',
            'kontak_2'         => 'nullable|string|max:20',
            'alamat'           => 'nullable|string',
            'kota'             => 'nullable|string|max:100',
            'status'           => 'required|in:Normal,Member,Redflag,Blacklist,Corporate,Rent to Rent',
            'has_apply_member' => 'boolean',
            'catatan'          => 'nullable|string',
        ];
    }
}
