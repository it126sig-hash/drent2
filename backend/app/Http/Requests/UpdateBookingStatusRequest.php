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
            'status' => ['required', 'string', 'in:follow_up,confirm,waiting_list,rental_unit,selesai,batal,reschedule'],
            'catatan_status' => [$this->status === 'reschedule' ? 'required' : 'nullable', 'string', 'max:500'],
            'reschedule_date' => ['nullable', 'date'],
            'returned_at' => ['nullable', 'date'],
        ];
    }
}
