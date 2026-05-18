<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicSignPhysicalCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_email' => ['required', 'email', 'max:150'],
            'otp_code' => ['required', 'string', 'size:6'],
            'signer_name' => ['nullable', 'string', 'max:100'],
            'signature_base64' => ['required', 'string'],
        ];
    }
}
