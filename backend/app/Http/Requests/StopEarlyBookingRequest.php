<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StopEarlyBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_detail_id' => 'required|exists:booking_details,id',
            'tgl_stop' => 'required|date',
            'refund_amount' => 'required|integer|min:0',
        ];
    }
}
