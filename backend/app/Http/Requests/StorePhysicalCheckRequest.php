<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePhysicalCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sections = ['front', 'left', 'right', 'rear', 'interior', 'km', 'fuel'];

        return [
            'booking_id' => ['required', 'exists:bookings,id'],
            'type' => ['required', 'string', 'in:departure,return'],
            'km_odometer' => ['required', 'integer', 'min:0'],
            'fuel_level' => ['nullable', 'string', 'max:50'],
            'fuel_marker_x' => ['nullable', 'numeric', 'between:0,100'],
            'fuel_marker_y' => ['nullable', 'numeric', 'between:0,100'],
            'notes' => ['nullable', 'string'],

            'sections' => ['required', 'array'],
            'sections.*.section' => ['required', Rule::in($sections)],
            'sections.*.notes' => ['nullable', 'string'],

            'photos' => ['nullable', 'array'],
            'photos.*.section' => ['required', Rule::in($sections)],
            'photos.*.image_base64' => ['required', 'string'],
            'photos.*.annotated_base64' => ['nullable', 'string'],
            'photos.*.notes' => ['nullable', 'string'],

            'checklist' => ['required', 'array', 'min:1'],
            'checklist.*.physical_check_item_id' => ['nullable', 'exists:physical_check_items,id'],
            'checklist.*.item_label' => ['required', 'string', 'max:100'],
            'checklist.*.is_present' => ['required', 'boolean'],
            'checklist.*.notes' => ['nullable', 'string'],

            'signatures' => ['required', 'array', 'size:2'],
            'signatures.*.signer_type' => ['required', 'string', 'in:inspector,customer_driver'],
            'signatures.*.signer_name' => ['nullable', 'string', 'max:100'],
            'signatures.*.signature_base64' => ['required', 'string'],
        ];
    }
}
