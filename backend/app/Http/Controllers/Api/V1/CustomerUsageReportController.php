<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CustomerUsageReportService;
use App\Services\PermissionService;
use Illuminate\Http\Request;

class CustomerUsageReportController extends Controller
{
    public function __construct(
        private CustomerUsageReportService $service,
        private PermissionService $permissionService
    ) {}

    /**
     * GET /api/v1/reports/customer-usage
     */
    public function __invoke(Request $request)
    {
        $user = auth()->user();
        abort_unless(
            $user->role === 'superadmin' ||
            $this->permissionService->hasPermission($user, 'finance.monthly_report'),
            403
        );

        $params = $request->only(['status', 'search', 'per_page']);

        if ($request->filled('customer_id')) {
            return response()->json(
                $this->service->transactions($params + ['customer_id' => $request->input('customer_id')])
            );
        }

        return response()->json($this->service->report($params));
    }
}
