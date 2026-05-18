<?php

namespace App\Http\Requests;

use App\Models\Driver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorized via Policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:8',
            'role'      => 'required|in:superadmin,admin_branch,supervisor,finance,driver_tetap,cs,teknisi',
            'branch_id' => 'required|exists:branches,id',
            'driver_id' => 'required_if:role,driver_tetap|nullable|exists:drivers,id',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('role') !== 'driver_tetap' || ! $this->filled('driver_id')) {
                    return;
                }

                $driver = Driver::query()->find($this->input('driver_id'));

                if (! $driver || $driver->tenant_id !== $this->user()->tenant_id) {
                    $validator->errors()->add('driver_id', 'Driver tidak valid.');
                    return;
                }

                if ((int) $driver->branch_id !== (int) $this->input('branch_id')) {
                    $validator->errors()->add('driver_id', 'Driver harus berada di branch yang sama.');
                }

                if (! $driver->is_tetap) {
                    $validator->errors()->add('driver_id', 'Pilih driver tetap.');
                }

                if ($driver->user_id) {
                    $validator->errors()->add('driver_id', 'Driver ini sudah memiliki akun user.');
                }
            },
        ];
    }
}
