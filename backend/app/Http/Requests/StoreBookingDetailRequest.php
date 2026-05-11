<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_id' => 'required|exists:units,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'tgl_sewa' => 'required|date',
            'tgl_kembali' => 'required|date|after_or_equal:tgl_sewa',
            'harga_mobil' => 'required|integer|min:0',
            'diskon_mobil' => 'nullable|integer|min:0',
            'detail_type' => 'required|string|in:initial,extend,rolling',
        ];
    }
}
