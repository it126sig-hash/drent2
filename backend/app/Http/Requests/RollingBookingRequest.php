<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RollingBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Step 1: adjust old detail
            'booking_detail_id'         => ['required', 'exists:booking_details,id'],
            'tgl_rolling'               => ['required', 'date'],
            'unit_id_lama'              => ['nullable', 'exists:units,id'],
            'driver_id_lama'            => ['nullable', 'exists:drivers,id'],
            'tgl_sewa_lama'             => ['nullable', 'date'],
            'tgl_kembali_lama'          => ['nullable', 'date'],
            'lama_sewa_lama'            => ['nullable', 'integer', 'min:1'],
            'paket_sewa_lama'           => ['nullable', 'string', 'in:harian,mingguan,bulanan'],
            'harga_mobil_lama'          => ['nullable', 'integer', 'min:0'],
            'diskon_mobil_lama'         => ['nullable', 'integer', 'min:0'],
            'pricing_mode_lama'         => ['nullable', 'string', 'in:all_in,non_all_in'],
            'pricing_package_id_lama'   => ['nullable', 'exists:pricing_packages,id'],
            'harga_all_in_lama'         => ['nullable', 'integer', 'min:0'],
            'costs_lama'                => ['nullable', 'array'],
            'costs_lama.*.cost_type_id' => ['nullable', 'exists:cost_types,id'],
            'costs_lama.*.type'         => ['nullable', 'string', 'in:biaya,diskon'],
            'costs_lama.*.label'        => ['required_with:costs_lama', 'string', 'max:255'],
            'costs_lama.*.amount'       => ['required_with:costs_lama', 'integer', 'min:0'],
            'costs_lama.*.keterangan'   => ['nullable', 'string'],

            // Step 2: new rolling detail
            'unit_id'                   => ['required', 'exists:units,id'],
            'driver_id'                 => ['nullable', 'exists:drivers,id'],
            'tgl_sewa'                  => ['required', 'date', 'after:tgl_rolling'],
            'tgl_kembali'               => ['required', 'date', 'after_or_equal:tgl_sewa'],
            'lama_sewa'                 => ['required', 'integer', 'min:1'],
            'paket_sewa'                => ['required', 'string', 'in:harian,mingguan,bulanan'],
            'harga_mobil'               => ['required', 'integer', 'min:0'],
            'diskon_mobil'              => ['nullable', 'integer', 'min:0'],
            'pricing_mode'              => ['required', 'string', 'in:all_in,non_all_in'],
            'pricing_package_id'        => ['nullable', 'exists:pricing_packages,id'],
            'harga_all_in'              => ['nullable', 'integer', 'min:0'],
            'costs'                     => ['nullable', 'array'],
            'costs.*.cost_type_id'      => ['nullable', 'exists:cost_types,id'],
            'costs.*.type'              => ['nullable', 'string', 'in:biaya,diskon'],
            'costs.*.label'             => ['required_with:costs', 'string', 'max:255'],
            'costs.*.amount'            => ['required_with:costs', 'integer', 'min:0'],
            'costs.*.keterangan'        => ['nullable', 'string'],
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

            if ($this->pricing_mode_lama === 'all_in' && empty($this->harga_all_in_lama) && empty($this->pricing_package_id_lama)) {
                $validator->errors()->add(
                    'harga_all_in_lama',
                    'Harga All In unit lama wajib diisi jika pricing mode adalah all_in dan tidak ada pricing package.'
                );
            }
        });
    }
}
