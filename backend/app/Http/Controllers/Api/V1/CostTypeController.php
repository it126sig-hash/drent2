<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreCostTypeRequest;
use App\Http\Requests\UpdateCostTypeRequest;
use App\Http\Resources\CostTypeResource;
use App\Models\CostType;
use App\Services\CostTypeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CostTypeController extends Controller
{
    use AuthorizesRequests;

    protected $service;

    public function __construct(CostTypeService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', CostType::class);
        $costTypes = $this->service->getAll($request->all());
        return CostTypeResource::collection($costTypes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCostTypeRequest $request)
    {
        $this->authorize('create', CostType::class);
        $costType = $this->service->create($request->validated());
        return new CostTypeResource($costType);
    }

    /**
     * Display the specified resource.
     */
    public function show(CostType $costType)
    {
        $this->authorize('view', $costType);
        return new CostTypeResource($costType);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCostTypeRequest $request, CostType $costType)
    {
        $this->authorize('update', $costType);
        $costType = $this->service->update($costType, $request->validated());
        return new CostTypeResource($costType);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CostType $costType)
    {
        $this->authorize('delete', $costType);
        $this->service->delete($costType);
        return response()->noContent();
    }
}
