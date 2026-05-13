<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lama_sewa'          => ['nullable', 'integer', 'min:1'],
            'paket_sewa'         => ['nullable', 'string', 'in:harian,mingguan,bulanan'],
            'harga_dealing'      => ['nullable', 'integer', 'min:0'],
            'dp'                 => ['nullable', 'integer', 'min:0'],
            'rekening_dp_id'     => ['nullable', 'exists:payment_accounts,id'],
            'unit_id'            => ['nullable', 'exists:units,id'],
            'unit_placeholder'   => ['required_without:unit_id', 'nullable', 'string', 'max:255'],
            'tgl_sewa'           => ['nullable', 'date'],
            'tgl_kembali'        => ['nullable', 'date', 'after:tgl_sewa'],
            'tujuan'             => ['nullable', 'string', 'max:500'],
            'kota'               => ['nullable', 'string', 'max:100'],
            'alamat_penjemputan' => ['nullable', 'string'],
            'catatan'            => ['nullable', 'string'],
        ];
    }
}
