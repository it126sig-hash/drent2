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
            'tujuan'             => ['nullable', 'string', 'max:500'],
            'alamat_penjemputan' => ['nullable', 'string'],
            'catatan'            => ['nullable', 'string'],
        ];
    }
}
