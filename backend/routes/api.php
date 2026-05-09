<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\RentalOwnerController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'branch.scope'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        
        Route::get('branches', [BranchController::class, 'index']);
        Route::apiResource('rental-owners', RentalOwnerController::class);
        
        // Users
        Route::get('roles', [UserController::class, 'roles']);
        Route::patch('users/{user}/reset-password', [UserController::class, 'resetPassword']);
        Route::apiResource('users', UserController::class);

        // Units
        Route::apiResource('units', UnitController::class);
        Route::post('units/{unit}/photos', [UnitController::class, 'uploadPhoto']);
        Route::delete('units/{unit}/photos/{photo}', [UnitController::class, 'deletePhoto']);

        // Drivers
        Route::apiResource('drivers', DriverController::class);
        Route::patch('drivers/{driver}/balance', [DriverController::class, 'updateBalance']);

        // Customers
        Route::apiResource('customers', CustomerController::class);

        // Members
        Route::apiResource('members', MemberController::class);
        Route::patch('members/{member}/activate', [MemberController::class, 'activate']);
        Route::get('members/{member}/documents/{type}', [MemberController::class, 'showDocument']);
    });
});
