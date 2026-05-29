<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class UpdateProfilePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! Hash::check((string) $this->input('current_password'), (string) $this->user()?->password)) {
                    $validator->errors()->add('current_password', 'Password lama tidak sesuai.');
                }
            },
        ];
    }
}
