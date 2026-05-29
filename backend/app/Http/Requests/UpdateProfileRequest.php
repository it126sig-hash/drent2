<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'no_rekening' => ['nullable', 'string', 'max:100'],
            'bank' => ['nullable', 'string', 'max:100'],
            'atas_nama' => ['nullable', 'string', 'max:255'],
            'kontak' => ['nullable', 'string', 'max:100'],
            'foto_profile' => ['nullable', 'image', 'max:2048'],
            'remove_foto_profile' => ['nullable', Rule::in(['0', '1', 0, 1, false, true])],
        ];
    }
}
