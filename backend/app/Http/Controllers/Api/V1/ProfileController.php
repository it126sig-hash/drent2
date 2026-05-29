<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfilePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\UserProfileService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly UserProfileService $service)
    {
    }

    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load(['branch', 'driver']));
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $this->service->updateProfile(
            $request->user(),
            $request->validated(),
            $request->file('foto_profile')
        );

        return new UserResource($user);
    }

    public function updatePassword(UpdateProfilePasswordRequest $request): UserResource
    {
        $user = $this->service->updatePassword($request->user(), $request->validated()['password']);

        return new UserResource($user);
    }
}
