<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\RentalOwnerController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\BookingDetailController;
use App\Http\Controllers\Api\V1\BookingCostController;
use App\Http\Controllers\Api\V1\BookingModificationController;
use App\Http\Controllers\Api\V1\PaymentAccountController;
use App\Http\Controllers\Api\V1\CostTypeController;
use App\Http\Controllers\Api\V1\PricingPackageController;
use App\Http\Controllers\Api\V1\BookingPaymentController;
use App\Http\Controllers\Api\V1\RefundController;
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

        // Master Data: Payment Accounts, Cost Types, Pricing Packages
        Route::apiResource('payment-accounts', PaymentAccountController::class);
        Route::apiResource('cost-types', CostTypeController::class);
        Route::apiResource('pricing-packages', PricingPackageController::class);

        // Booking Payments (C1)
        Route::get('bookings/{booking}/payments', [BookingPaymentController::class, 'index']);
        Route::post('bookings/{booking}/payments', [BookingPaymentController::class, 'store']);
        Route::post('booking-payments/{bookingPayment}/reallocate', [BookingPaymentController::class, 'reallocate']);

        // Refunds (C2)
        Route::get('bookings/{booking}/refunds', [RefundController::class, 'index']);
        Route::post('bookings/{booking}/refund', [RefundController::class, 'store']);

        // Bookings
        Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus']);
        Route::patch('bookings/{booking}/handle', [BookingController::class, 'handle']);
        Route::post('bookings/{booking}/checkout', [BookingController::class, 'checkout']);
        Route::post('bookings/{booking}/complete', [BookingController::class, 'complete']);
        Route::post('bookings/{booking}/details', [BookingDetailController::class, 'store']);
        Route::patch('booking-details/{bookingDetail}', [BookingDetailController::class, 'update']);
        Route::post('booking-details/{bookingDetail}/costs', [BookingCostController::class, 'store']);
        Route::patch('booking-costs/{bookingCost}', [BookingCostController::class, 'update']);
        
        // Modification routes
        Route::post('bookings/{booking}/extend', [BookingModificationController::class, 'extend']);
        Route::post('bookings/{booking}/rolling', [BookingModificationController::class, 'rolling']);
        Route::post('bookings/{booking}/stop-early', [BookingModificationController::class, 'stopEarly']);
        Route::post('bookings/{booking}/cancel', [BookingModificationController::class, 'cancel']);
        Route::post('bookings/{booking}/costs', [BookingCostController::class, 'storeAdditionalCost']);

        Route::apiResource('bookings', BookingController::class);
    });
});
