<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by Policy
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:follow_up,confirm,waiting_list,rental_unit,selesai,batal'],
            'catatan_status' => ['nullable', 'string', 'max:500'],
        ];
    }
}
