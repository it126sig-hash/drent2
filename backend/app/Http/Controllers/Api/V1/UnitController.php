<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use App\Models\UnitPhoto;
use App\Services\UnitService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UnitController extends Controller
{
    use AuthorizesRequests;

    protected $service;

    public function __construct(UnitService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Unit::class);
        $units = $this->service->getAll($request->all());
        return UnitResource::collection($units);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUnitRequest $request)
    {
        $this->authorize('create', Unit::class);
        $unit = $this->service->create($request->validated());
        return new UnitResource($unit);
    }

    /**
     * Display the specified resource.
     */
    public function show(Unit $unit)
    {
        $this->authorize('view', $unit);
        $unit->load(['rentalOwner', 'photos']);
        return new UnitResource($unit);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUnitRequest $request, Unit $unit)
    {
        $this->authorize('update', $unit);
        $unit = $this->service->update($unit, $request->validated());
        return new UnitResource($unit);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Unit $unit)
    {
        $this->authorize('delete', $unit);
        $this->service->delete($unit);
        return response()->noContent();
    }

    /**
     * Upload photo for unit.
     */
    public function uploadPhoto(Request $request, Unit $unit)
    {
        $this->authorize('uploadPhoto', $unit);
        
        $request->validate([
            'photo' => 'required|image|max:5120',
            'label' => 'nullable|string|max:50',
        ]);

        $photo = $this->service->uploadPhoto($unit, $request->file('photo'), $request->label);
        return response()->json([
            'data' => [
                'id' => $photo->id,
                'url' => asset('storage/' . $photo->path),
                'label' => $photo->label,
            ],
            'message' => 'Foto berhasil diunggah'
        ]);
    }

    /**
     * Delete photo from unit.
     */
    public function deletePhoto(Unit $unit, UnitPhoto $photo)
    {
        $this->authorize('update', $unit);
        
        if ($photo->unit_id !== $unit->id) {
            return response()->json(['message' => 'Foto tidak ditemukan pada unit ini'], 404);
        }

        $this->service->deletePhoto($photo);
        return response()->noContent();
    }
}
