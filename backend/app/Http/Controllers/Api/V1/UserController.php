<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserController extends Controller
{
    use AuthorizesRequests;

    protected $service;

    public function __construct(UserService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);
        $users = $this->service->getAll($request->all());
        return UserResource::collection($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $this->authorize('create', User::class);
        $user = $this->service->create($request->validated());
        return new UserResource($user);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);
        return new UserResource($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);
        $user = $this->service->update($user, $request->validated());
        return new UserResource($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);
        $this->service->delete($user);
        return response()->noContent();
    }

    /**
     * Reset user password.
     */
    public function resetPassword(ResetPasswordRequest $request, User $user)
    {
        $this->authorize('resetPassword', $user);
        $user = $this->service->resetPassword($user, $request->validated()['password']);
        return new UserResource($user);
    }

    /**
     * Get list of valid roles.
     */
    public function roles(Request $request)
    {
        $this->authorize('viewAny', User::class);
        
        $roles = [
            ['value' => 'superadmin',   'label' => 'Super Admin'],
            ['value' => 'admin_branch', 'label' => 'Admin Branch'],
            ['value' => 'supervisor',   'label' => 'Supervisor'],
            ['value' => 'finance',      'label' => 'Finance'],
            ['value' => 'driver_tetap', 'label' => 'Driver Tetap'],
            ['value' => 'cs',           'label' => 'Customer Service'],
            ['value' => 'teknisi',      'label' => 'Teknisi'],
        ];
        
        return response()->json(['data' => $roles]);
    }
}
