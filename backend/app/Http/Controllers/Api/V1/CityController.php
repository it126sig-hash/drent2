<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCityRequest;
use App\Http\Requests\UpdateCityRequest;
use App\Http\Resources\CityResource;
use App\Models\City;
use App\Services\CityService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class CityController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private CityService $service)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', City::class);
        $cities = $this->service->getAll($request->only(['search', 'is_active', 'per_page']));

        return CityResource::collection($cities);
    }

    public function store(StoreCityRequest $request)
    {
        $this->authorize('create', City::class);
        $city = $this->service->create($request->validated());

        return new CityResource($city);
    }

    public function show(City $city)
    {
        $this->authorize('view', $city);

        return new CityResource($city);
    }

    public function update(UpdateCityRequest $request, City $city)
    {
        $this->authorize('update', $city);
        $city = $this->service->update($city, $request->validated());

        return new CityResource($city);
    }

    public function destroy(City $city)
    {
        $this->authorize('delete', $city);
        $this->service->delete($city);

        return response()->noContent();
    }
}
