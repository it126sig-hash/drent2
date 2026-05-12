<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingDetailRequest extends FormRequest
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
            'unit_id' => 'required|exists:units,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'tgl_sewa' => 'required|date',
            'tgl_kembali' => 'required|date|after_or_equal:tgl_sewa',
            'harga_mobil' => 'required|numeric|min:0',
            'diskon_mobil' => 'nullable|numeric|min:0',
            'lama_sewa' => 'required|integer|min:1',
            'paket_sewa' => 'required|string|in:harian,mingguan,bulanan',
            'pricing_mode' => 'required|string|in:all_in,non_all_in',
            'pricing_package_id' => 'nullable|exists:pricing_packages,id',
            'harga_all_in' => 'nullable|integer|min:0',
            'costs' => 'nullable|array',
            'costs.*.cost_type_id' => 'nullable|exists:cost_types,id',
            'costs.*.label' => 'required_with:costs|string|max:255',
            'costs.*.amount' => 'required_with:costs|integer|min:0',
            'costs.*.keterangan' => 'nullable|string',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->pricing_mode === 'all_in' && empty($this->harga_all_in) && empty($this->pricing_package_id)) {
                $validator->errors()->add('harga_all_in', 'Harga All In wajib diisi jika tidak memilih pricing package.');
            }
        });
    }
}
