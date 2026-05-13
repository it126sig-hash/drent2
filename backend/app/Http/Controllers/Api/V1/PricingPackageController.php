<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StorePricingPackageRequest;
use App\Http\Requests\UpdatePricingPackageRequest;
use App\Http\Resources\PricingPackageResource;
use App\Models\PricingPackage;
use App\Services\PricingPackageService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PricingPackageController extends Controller
{
    use AuthorizesRequests;

    protected $service;

    public function __construct(PricingPackageService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', PricingPackage::class);
        $packages = $this->service->getAll($request->all());
        return PricingPackageResource::collection($packages);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePricingPackageRequest $request)
    {
        $this->authorize('create', PricingPackage::class);
        $package = $this->service->create($request->validated());
        return new PricingPackageResource($package);
    }

    /**
     * Display the specified resource.
     */
    public function show(PricingPackage $pricingPackage)
    {
        $this->authorize('view', $pricingPackage);
        return new PricingPackageResource($pricingPackage->loadMissing(['costType', 'items.costType']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePricingPackageRequest $request, PricingPackage $pricingPackage)
    {
        $this->authorize('update', $pricingPackage);
        $pricingPackage = $this->service->update($pricingPackage, $request->validated());
        return new PricingPackageResource($pricingPackage);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PricingPackage $pricingPackage)
    {
        $this->authorize('delete', $pricingPackage);
        $this->service->delete($pricingPackage);
        return response()->noContent();
    }
}
