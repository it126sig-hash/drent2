<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardRequest;
use App\Http\Resources\DashboardResource;
use App\Models\Booking;
use App\Services\DashboardService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class DashboardController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private DashboardService $service)
    {
    }

    public function index(DashboardRequest $request): DashboardResource
    {
        $this->authorize('viewAny', Booking::class);

        return new DashboardResource($this->service->summary(
            $request->validated(),
            $request->user()
        ));
    }
}
