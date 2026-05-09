<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRentalOwnerRequest;
use App\Http\Requests\UpdateRentalOwnerRequest;
use App\Http\Resources\RentalOwnerResource;
use App\Models\RentalOwner;
use App\Services\RentalOwnerService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class RentalOwnerController extends Controller
{
    use AuthorizesRequests;

    protected $service;

    public function __construct(RentalOwnerService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', RentalOwner::class);
        $owners = $this->service->getAll($request->all());
        return RentalOwnerResource::collection($owners);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRentalOwnerRequest $request)
    {
        $this->authorize('create', RentalOwner::class);
        $owner = $this->service->create($request->validated());
        return new RentalOwnerResource($owner);
    }

    /**
     * Display the specified resource.
     */
    public function show(RentalOwner $rentalOwner)
    {
        $this->authorize('view', $rentalOwner);
        return new RentalOwnerResource($rentalOwner);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRentalOwnerRequest $request, RentalOwner $rentalOwner)
    {
        $this->authorize('update', $rentalOwner);
        $owner = $this->service->update($rentalOwner, $request->validated());
        return new RentalOwnerResource($owner);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RentalOwner $rentalOwner)
    {
        $this->authorize('delete', $rentalOwner);
        $this->service->delete($rentalOwner);
        return response()->noContent();
    }
}
