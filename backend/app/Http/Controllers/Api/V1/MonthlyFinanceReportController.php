<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MonthlyFinanceReportRequest;
use App\Http\Resources\MonthlyFinanceReportResource;
use App\Models\PaymentAccountTransaction;
use App\Services\MonthlyFinanceReportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MonthlyFinanceReportController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private MonthlyFinanceReportService $service) {}

    public function __invoke(MonthlyFinanceReportRequest $request)
    {
        $this->authorize('viewAny', PaymentAccountTransaction::class);

        return new MonthlyFinanceReportResource($this->service->report($request->validated()));
    }
}
