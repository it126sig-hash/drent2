<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MergeInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_ids'          => ['required', 'array', 'min:2'],
            'invoice_ids.*'        => ['integer', 'distinct', 'exists:invoices,id'],
            'due_date'             => ['nullable', 'date'],
            'terms_and_conditions' => ['nullable', 'string'],
        ];
    }
}
