<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDriverRequest extends FormRequest
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
            'nama'     => 'required|string|max:150',
            'kontak_1' => 'required|string|max:20',
            'kontak_2' => 'nullable|string|max:20',
            'alamat'   => 'nullable|string',
            'kota'     => 'nullable|string|max:100',
            'no_sim'   => 'nullable|string|max:30',
            'status'   => 'required|in:Aktif,Tidak Aktif',
            'is_tetap' => 'required|boolean',
            'catatan'  => 'nullable|string',
            'user_id'  => 'nullable|exists:users,id',
            'branch_id' => 'nullable|exists:branches,id',
        ];
    }
}
