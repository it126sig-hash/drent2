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
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\PricingPackageController;
use App\Http\Controllers\Api\V1\BookingPaymentController;
use App\Http\Controllers\Api\V1\RefundController;
use App\Http\Controllers\Api\V1\PhysicalCheckController;
use App\Http\Controllers\Api\V1\PhysicalCheckItemController;
use App\Http\Controllers\Api\V1\ReceivableController;
use App\Http\Controllers\Api\V1\SupervisorRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('public/invoices/{token}', [ReceivableController::class, 'publicInvoice']);
    Route::get('public/physical-checks/{token}', [PhysicalCheckController::class, 'publicShow']);
    Route::post('public/physical-checks/{token}/otp', [PhysicalCheckController::class, 'publicRequestOtp']);
    Route::post('public/physical-checks/{token}/activities', [PhysicalCheckController::class, 'publicActivity']);
    Route::post('public/physical-checks/{token}/submit', [PhysicalCheckController::class, 'publicStore']);

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
        Route::apiResource('cities', CityController::class);
        Route::apiResource('cost-types', CostTypeController::class);
        Route::apiResource('pricing-packages', PricingPackageController::class);

        // Physical Checks
        Route::get('physical-check-items', [PhysicalCheckItemController::class, 'index']);
        Route::get('physical-checks/bookings', [PhysicalCheckController::class, 'bookings']);
        Route::post('physical-checks/request', [PhysicalCheckController::class, 'request']);
        Route::post('physical-checks', [PhysicalCheckController::class, 'store']);
        Route::get('bookings/{booking}/physical-checks/{type}', [PhysicalCheckController::class, 'showByBooking']);
        Route::get('physical-checks/{physicalCheck}', [PhysicalCheckController::class, 'show']);

        // Booking Payments (C1)
        Route::get('bookings/{booking}/payments', [BookingPaymentController::class, 'index']);
        Route::post('bookings/{booking}/payments', [BookingPaymentController::class, 'store']);
        Route::post('booking-payments/{bookingPayment}/reallocate', [BookingPaymentController::class, 'reallocate']);
        Route::post('booking-payments/{bookingPayment}/request-void', [BookingPaymentController::class, 'requestVoid']);
        Route::post('booking-payments/{bookingPayment}/approve-void', [BookingPaymentController::class, 'approveVoid']);
        Route::post('booking-payments/{bookingPayment}/reject-void', [BookingPaymentController::class, 'rejectVoid']);

        // Refunds (C2)
        Route::get('bookings/{booking}/refunds', [RefundController::class, 'index']);
        Route::post('bookings/{booking}/refund', [RefundController::class, 'store']);

        // Receivables & Invoices
        Route::get('receivables', [ReceivableController::class, 'index']);
        Route::post('receivables/invoices', [ReceivableController::class, 'generateInvoice']);
        Route::get('invoices', [ReceivableController::class, 'invoices']);
        Route::get('invoices/{invoice}/pdf', [ReceivableController::class, 'invoicePdf']);
        Route::post('invoices/{invoice}/mark-sent', [ReceivableController::class, 'markInvoiceSent']);
        Route::post('invoices/{invoice}/payments', [ReceivableController::class, 'storeInvoicePayment']);

        // Supervisor approval inbox
        Route::get('supervisor-requests', [SupervisorRequestController::class, 'index']);

        // Bookings
        Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus']);
        Route::patch('bookings/{booking}/handle', [BookingController::class, 'handle']);
        Route::post('bookings/{booking}/checkout', [BookingController::class, 'checkout']);
        Route::post('bookings/{booking}/complete', [BookingController::class, 'complete']);
        Route::post('bookings/{booking}/request-rental-unit-return', [BookingController::class, 'requestRentalUnitReturn']);
        Route::post('bookings/{booking}/approve-rental-unit-return', [BookingController::class, 'approveRentalUnitReturn']);
        Route::post('bookings/{booking}/reject-rental-unit-return', [BookingController::class, 'rejectRentalUnitReturn']);
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
