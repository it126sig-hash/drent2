<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Services\BranchService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly BranchService $service)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Branch::class);

        $branches = $this->service->getAll($request->only(['search', 'per_page']));

        return BranchResource::collection($branches);
    }

    public function show(Branch $branch): BranchResource
    {
        $this->authorize('view', $branch);

        return new BranchResource($branch->load('city'));
    }

    public function store(StoreBranchRequest $request): BranchResource
    {
        $this->authorize('create', Branch::class);

        $branch = $this->service->create(
            $request->validated(),
            $request->file('logo')
        );

        return new BranchResource($branch);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): BranchResource
    {
        $this->authorize('update', $branch);

        $branch = $this->service->update(
            $branch,
            $request->validated(),
            $request->file('logo')
        );

        return new BranchResource($branch);
    }

    public function destroy(Branch $branch)
    {
        $this->authorize('delete', $branch);

        $this->service->delete($branch);

        return response()->noContent();
    }
}
