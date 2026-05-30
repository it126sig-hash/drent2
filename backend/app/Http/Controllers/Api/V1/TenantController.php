<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly TenantService $service)
    {
    }

    /**
     * Tampilkan profil tenant milik user yang login.
     */
    public function show(Request $request): TenantResource
    {
        $tenant = Tenant::with('city')->findOrFail($request->user()->tenant_id);
        $this->authorize('view', $tenant);

        return new TenantResource($tenant);
    }

    /**
     * Update profil tenant milik user yang login.
     */
    public function update(UpdateTenantRequest $request): TenantResource
    {
        $tenant = Tenant::findOrFail($request->user()->tenant_id);
        $this->authorize('update', $tenant);

        $tenant = $this->service->update(
            $tenant,
            $request->validated(),
            $request->file('logo')
        );

        return new TenantResource($tenant);
    }
}
