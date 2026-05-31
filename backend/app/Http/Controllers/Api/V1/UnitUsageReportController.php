<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PermissionService;
use App\Services\UnitUsageReportService;
use Illuminate\Http\Request;

class UnitUsageReportController extends Controller
{
    public function __construct(
        private UnitUsageReportService $service,
        private PermissionService $permissionService
    ) {}

    /**
     * GET /api/v1/reports/unit-usage
     */
    public function __invoke(Request $request)
    {
        $user = auth()->user();
        abort_unless(
            $user->role === 'superadmin' ||
            $this->permissionService->hasPermission($user, 'finance.monthly_report'),
            403
        );

        $params = $request->only([
            'mode', 'date_from', 'date_to', 'city_id', 'rental_owner_id', 'search', 'per_page',
        ]);

        if ($request->filled('unit_id')) {
            return response()->json(
                $this->service->transactions($params + ['unit_id' => $request->input('unit_id')])
            );
        }

        return response()->json($this->service->report($params));
    }
}
