<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InvoiceTermsTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceTermsTemplateController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $templates = InvoiceTermsTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'content', 'is_default', 'is_active']);

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'content'    => ['required', 'string'],
            'is_default' => ['boolean'],
        ]);

        $user = auth()->user();

        $template = DB::transaction(function () use ($validated, $user) {
            if ($validated['is_default'] ?? false) {
                InvoiceTermsTemplate::where('tenant_id', $user->tenant_id)
                    ->update(['is_default' => false]);
            }

            return InvoiceTermsTemplate::create([
                'tenant_id'  => $user->tenant_id,
                'name'       => $validated['name'],
                'content'    => $validated['content'],
                'is_default' => $validated['is_default'] ?? false,
                'is_active'  => true,
                'created_by' => $user->id,
            ]);
        });

        return response()->json(['data' => $template], 201);
    }

    public function update(Request $request, InvoiceTermsTemplate $invoiceTermsTemplate)
    {
        $this->ensureSameTenant($invoiceTermsTemplate);

        $validated = $request->validate([
            'name'       => ['sometimes', 'required', 'string', 'max:255'],
            'content'    => ['sometimes', 'required', 'string'],
            'is_default' => ['boolean'],
            'is_active'  => ['boolean'],
        ]);

        $user = auth()->user();

        $template = DB::transaction(function () use ($validated, $invoiceTermsTemplate, $user) {
            if ($validated['is_default'] ?? false) {
                InvoiceTermsTemplate::where('tenant_id', $user->tenant_id)
                    ->where('id', '!=', $invoiceTermsTemplate->id)
                    ->update(['is_default' => false]);
            }

            $invoiceTermsTemplate->update($validated);
            return $invoiceTermsTemplate->fresh();
        });

        return response()->json(['data' => $template]);
    }

    public function destroy(InvoiceTermsTemplate $invoiceTermsTemplate)
    {
        $this->ensureSameTenant($invoiceTermsTemplate);
        $invoiceTermsTemplate->delete();

        return response()->json(null, 204);
    }

    private function ensureSameTenant(InvoiceTermsTemplate $template): void
    {
        abort_if($template->tenant_id !== auth()->user()->tenant_id, 403);
    }
}
