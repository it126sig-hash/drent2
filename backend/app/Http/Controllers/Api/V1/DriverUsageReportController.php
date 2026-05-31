<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DriverUsageReportService;
use App\Services\PermissionService;
use Illuminate\Http\Request;

class DriverUsageReportController extends Controller
{
    public function __construct(
        private DriverUsageReportService $service,
        private PermissionService $permissionService
    ) {}

    /**
     * GET /api/v1/reports/driver-usage
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
            'mode', 'date_from', 'date_to', 'kota', 'search', 'per_page',
        ]);

        if ($request->filled('driver_id')) {
            return response()->json(
                $this->service->transactions($params + ['driver_id' => $request->input('driver_id')])
            );
        }

        return response()->json($this->service->report($params));
    }
}
