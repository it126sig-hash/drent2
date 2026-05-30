<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateInvoiceRequest;
use App\Http\Requests\RefreshInvoiceAmountRequest;
use App\Http\Requests\StoreInvoicePaymentRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PublicInvoiceResource;
use App\Http\Resources\ReceivableResource;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use App\Services\ReceivableService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class ReceivableController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ReceivableService $receivableService,
        private InvoicePdfService $invoicePdfService
    )
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $filters = $request->only([
            'page',
            'per_page',
            'invoice_status',
            'search',
            'kota',
        ]);

        $user = auth()->user();
        $filters['tenant_id'] = $user->tenant_id;
        if ($user->role !== 'superadmin') {
            $filters['branch_id'] = $user->branch_id;
        }

        $receivables = $this->receivableService->list($filters);

        return ReceivableResource::collection($receivables);
    }

    public function generateInvoice(GenerateInvoiceRequest $request): InvoiceResource
    {
        $this->authorize('create', Invoice::class);

        $user = auth()->user();
        try {
            $invoice = $this->receivableService->generateInvoice(
                $request->validated('booking_ids'),
                $user->branch_id,
                $user->tenant_id,
                $request->validated('due_date'),
                $request->validated('terms_and_conditions')
            );
        } catch (\InvalidArgumentException $exception) {
            abort(response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['booking_ids' => [$exception->getMessage()]],
            ], 422));
        }

        return new InvoiceResource($invoice);
    }

    public function invoices(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $filters = $request->only(['page', 'per_page', 'status', 'search', 'kota']);
        $user = auth()->user();
        $filters['tenant_id'] = $user->tenant_id;
        if ($user->role !== 'superadmin') {
            $filters['branch_id'] = $user->branch_id;
        }

        return InvoiceResource::collection($this->receivableService->invoices($filters));
    }

    public function paymentHistory(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $filters = $request->only([
            'view',
            'latest_page',
            'latest_per_page',
            'latest_limit',
            'group_page',
            'group_per_page',
            'group_limit',
        ]);
        $user = auth()->user();
        $filters['tenant_id'] = $user->tenant_id;
        if ($user->role !== 'superadmin') {
            $filters['branch_id'] = $user->branch_id;
        }

        return response()->json([
            'data' => $this->receivableService->paymentHistory($filters),
        ]);
    }

    public function markInvoiceSent(Invoice $invoice): InvoiceResource
    {
        $this->authorize('update', $invoice);

        return new InvoiceResource($this->receivableService->markSent($invoice));
    }

    public function refreshInvoiceAmount(RefreshInvoiceAmountRequest $request, Invoice $invoice): InvoiceResource
    {
        $this->authorize('update', $invoice);

        try {
            return new InvoiceResource($this->receivableService->refreshInvoiceAmount(
                $invoice,
                (bool) $request->boolean('confirm_sent_revision')
            ));
        } catch (\InvalidArgumentException $exception) {
            abort(response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['invoice' => [$exception->getMessage()]],
            ], 422));
        }
    }

    public function storeInvoicePayment(StoreInvoicePaymentRequest $request, Invoice $invoice): InvoiceResource
    {
        $this->authorize('update', $invoice);

        try {
            return new InvoiceResource($this->receivableService->storePayment($invoice, $request->validated()));
        } catch (\InvalidArgumentException $exception) {
            abort(response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['amount' => [$exception->getMessage()]],
            ], 422));
        }
    }

    public function invoicePdf(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $pdf = $this->invoicePdfService->make($invoice);
        $filename = $invoice->invoice_number . '.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function invoiceHistories(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $histories = $invoice->histories()->with('actor')->get();

        return response()->json([
            'data' => $histories->map(fn($h) => [
                'id'             => $h->id,
                'event_type'     => $h->event_type,
                'description'    => $h->description,
                'amount_before'  => $h->amount_before,
                'amount_after'   => $h->amount_after,
                'payment_amount' => $h->payment_amount,
                'actor_name'     => $h->actor?->name,
                'created_at'     => $h->created_at?->toISOString(),
            ]),
        ]);
    }

    public function publicInvoice(Request $request, string $token): PublicInvoiceResource
    {
        [$invoice, $paymentAccounts] = $this->receivableService->publicInvoice($token);

        return new PublicInvoiceResource($invoice, $paymentAccounts);
    }
}
