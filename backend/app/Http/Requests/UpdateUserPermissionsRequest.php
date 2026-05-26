<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'overrides' => 'present|array',
            'overrides.*.key' => 'required|string',
            'overrides.*.value' => 'nullable|in:grant,revoke'
        ];
    }
}
