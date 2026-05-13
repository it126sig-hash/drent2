<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HandleBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_id'               => ['required', 'exists:units,id'],
            'driver_id'             => ['nullable', 'exists:drivers,id'],
            'lama_sewa'             => ['required', 'integer', 'min:1'],
            'paket_sewa'            => ['required', 'string', 'in:harian,mingguan,bulanan'],
            'harga_mobil'           => ['required', 'integer', 'min:0'],
            'diskon_mobil'          => ['nullable', 'integer', 'min:0'],
            'pricing_mode'          => ['required', 'string', 'in:all_in,non_all_in'],
            'pricing_package_id'    => ['nullable', 'exists:pricing_packages,id'],
            'harga_all_in'          => ['nullable', 'integer', 'min:0'],
            'costs'                 => ['nullable', 'array'],
            'costs.*.cost_type_id'  => ['nullable', 'exists:cost_types,id'],
            'costs.*.type'          => ['nullable', 'string', 'in:biaya,diskon'],
            'costs.*.label'         => ['required_with:costs', 'string', 'max:255'],
            'costs.*.amount'        => ['required_with:costs', 'integer', 'min:0'],
            'costs.*.keterangan'    => ['nullable', 'string'],
            'alamat_penjemputan'    => ['nullable', 'string', 'max:500'],
            'tujuan'                => ['nullable', 'string', 'max:500'],
            'kota'                  => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->pricing_mode === 'all_in' && empty($this->harga_all_in) && empty($this->pricing_package_id)) {
                $validator->errors()->add(
                    'harga_all_in',
                    'Harga All In wajib diisi jika pricing mode adalah all_in dan tidak ada pricing package.'
                );
            }
        });
    }
}
