<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Requests\StoreDriverRequest;
use App\Http\Requests\UpdateDriverRequest;
use App\Http\Requests\UpdateDriverBalanceRequest;
use App\Http\Resources\DriverResource;
use App\Models\Driver;
use App\Services\DriverService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class DriverController extends Controller
{
    use AuthorizesRequests;

    protected $service;

    public function __construct(DriverService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Driver::class);
        $drivers = $this->service->getAll($request->all());
        return DriverResource::collection($drivers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDriverRequest $request)
    {
        $this->authorize('create', Driver::class);
        $driver = $this->service->create($request->validated());
        return new DriverResource($driver);
    }

    /**
     * Display the specified resource.
     */
    public function show(Driver $driver)
    {
        $this->authorize('view', $driver);
        return new DriverResource($driver);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDriverRequest $request, Driver $driver)
    {
        $this->authorize('update', $driver);
        $driver = $this->service->update($driver, $request->validated());
        return new DriverResource($driver);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Driver $driver)
    {
        $this->authorize('delete', $driver);
        $this->service->delete($driver);
        return response()->noContent();
    }

    /**
     * Update driver balance.
     */
    public function updateBalance(UpdateDriverBalanceRequest $request, Driver $driver)
    {
        $this->authorize('updateBalance', $driver);
        $driver = $this->service->updateBalance($driver, $request->validated()['saldo']);
        return new DriverResource($driver);
    }
}
