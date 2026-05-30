<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = auth()->user()?->tenant_id;

        return [
            'name'      => ['required', 'string', 'max:255'],
            'address'   => ['nullable', 'string', 'max:1000'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'phone_alt' => ['nullable', 'string', 'max:30'],
            'email'     => ['nullable', 'email', 'max:255'],
            'website'   => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'tiktok'    => ['nullable', 'url', 'max:255'],
            'facebook'  => ['nullable', 'url', 'max:255'],
            'city_id'   => [
                'nullable',
                'integer',
                Rule::exists('cities', 'id')->where('tenant_id', $tenantId),
            ],
            'logo'      => ['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'Nama cabang wajib diisi.',
            'email.email'    => 'Format email tidak valid.',
            'website.url'    => 'Format URL website tidak valid.',
            'instagram.url'  => 'Instagram harus berupa URL lengkap (https://...).',
            'tiktok.url'     => 'TikTok harus berupa URL lengkap (https://...).',
            'facebook.url'   => 'Facebook harus berupa URL lengkap (https://...).',
            'logo.image'     => 'Logo harus berupa file gambar.',
            'logo.max'       => 'Logo maksimal 2MB.',
            'logo.mimes'     => 'Logo hanya boleh: jpg, jpeg, png, webp.',
            'city_id.exists' => 'Kota yang dipilih tidak valid.',
        ];
    }
}
