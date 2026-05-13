<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

use App\Traits\PhoneNormalizer;

class StoreBookingRequest extends FormRequest
{
    use PhoneNormalizer;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorized by Policy in Controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => 'nullable|exists:customers,id',
            'rental_owner_id' => 'nullable|exists:rental_owners,id',
            'customer_name' => 'required_without_all:customer_id,rental_owner_id|string|max:255',
            'customer_phone' => [
                'required_without_all:customer_id,rental_owner_id',
                'string',
                'min:9',
                'max:15',
                function ($attribute, $value, $fail) {
                    if (!$this->customer_id && $value) {
                        $normalized = $this->normalizePhone($value);
                        $exists = \App\Models\Customer::where('kontak_1', $normalized)->exists();
                        if ($exists) {
                            $fail('Nomor telepon sudah terdaftar. Gunakan pelanggan existing.');
                        }
                    }
                }
            ],
            'customer_city' => 'nullable|string|max:255',
            'unit_id' => 'nullable|exists:units,id',
            'unit_placeholder' => 'required_without:unit_id|string|max:255',
            'tgl_sewa' => 'required|date|after_or_equal:today',
            'tgl_kembali' => 'required|date|after:tgl_sewa',
            'lama_sewa' => 'nullable|integer|min:1',
            'paket_sewa' => 'nullable|in:harian,mingguan,bulanan',
            'tujuan' => 'nullable|string|max:255',
            'alamat_penjemputan' => 'nullable|string',
            'harga_dealing' => 'nullable|integer|min:0',
            'dp' => 'nullable|integer|min:0',
            'rekening_dp_id' => 'required_with:dp|nullable|exists:payment_accounts,id',
            'catatan' => 'nullable|string',
        ];
    }
}
