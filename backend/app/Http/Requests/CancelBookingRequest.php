<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ada_refund'      => ['boolean'],
            'nominal_refund'  => ['nullable', 'integer', 'min:0', 'required_if:ada_refund,true'],
            'bank_refund'     => ['nullable', 'string', 'max:100'],
            'no_rek_refund'   => ['nullable', 'string', 'max:50'],
            'nama_rek_refund' => ['nullable', 'string', 'max:100'],
            'catatan_refund'  => ['nullable', 'string', 'max:500'],
        ];
    }
}
