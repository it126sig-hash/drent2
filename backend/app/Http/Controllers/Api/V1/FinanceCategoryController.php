<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinanceCategoryRequest;
use App\Http\Requests\UpdateFinanceCategoryRequest;
use App\Http\Resources\FinanceCategoryResource;
use App\Models\FinanceCategory;
use App\Services\FinanceCategoryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class FinanceCategoryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private FinanceCategoryService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', FinanceCategory::class);

        return FinanceCategoryResource::collection($this->service->getAll($request->all()));
    }

    public function store(StoreFinanceCategoryRequest $request)
    {
        $this->authorize('create', FinanceCategory::class);

        return new FinanceCategoryResource($this->service->create($request->validated()));
    }

    public function show(FinanceCategory $financeCategory)
    {
        $this->authorize('view', $financeCategory);

        return new FinanceCategoryResource($financeCategory);
    }

    public function update(UpdateFinanceCategoryRequest $request, FinanceCategory $financeCategory)
    {
        $this->authorize('update', $financeCategory);

        return new FinanceCategoryResource($this->service->update($financeCategory, $request->validated()));
    }

    public function destroy(FinanceCategory $financeCategory)
    {
        $this->authorize('delete', $financeCategory);
        $this->service->delete($financeCategory);

        return response()->noContent();
    }
}
