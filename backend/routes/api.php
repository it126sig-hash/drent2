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
use App\Http\Controllers\Api\V1\RentToRentController;
use App\Http\Controllers\Api\V1\SupervisorRequestController;
use App\Http\Controllers\Api\V1\DriverOperationalFundController;
use App\Http\Controllers\Api\V1\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('public/invoices/{token}', [ReceivableController::class, 'publicInvoice']);
    Route::get('public/rent-to-rent-bills/{token}', [RentToRentController::class, 'publicBill']);
    Route::get('public/rent-to-rent-bills/{token}/pdf', [RentToRentController::class, 'publicBillPdf']);
    Route::get('public/physical-checks/{token}', [PhysicalCheckController::class, 'publicShow']);
    Route::post('public/physical-checks/{token}/otp', [PhysicalCheckController::class, 'publicRequestOtp']);
    Route::post('public/physical-checks/{token}/activities', [PhysicalCheckController::class, 'publicActivity']);
    Route::post('public/physical-checks/{token}/submit', [PhysicalCheckController::class, 'publicStore']);

    Route::middleware(['auth:sanctum', 'branch.scope'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('dashboard', [DashboardController::class, 'index']);
        
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
        Route::get('receivables/payment-history', [ReceivableController::class, 'paymentHistory']);
        Route::post('receivables/invoices', [ReceivableController::class, 'generateInvoice']);
        Route::get('invoices', [ReceivableController::class, 'invoices']);
        Route::get('invoices/{invoice}/pdf', [ReceivableController::class, 'invoicePdf']);
        Route::post('invoices/{invoice}/mark-sent', [ReceivableController::class, 'markInvoiceSent']);
        Route::post('invoices/{invoice}/refresh-amount', [ReceivableController::class, 'refreshInvoiceAmount']);
        Route::post('invoices/{invoice}/payments', [ReceivableController::class, 'storeInvoicePayment']);

        // Rent-to-rent payables
        Route::get('rent-to-rent', [RentToRentController::class, 'index']);
        Route::get('rent-to-rent/payment-history', [RentToRentController::class, 'paymentHistory']);
        Route::post('rent-to-rent/payments/{payment}/void', [RentToRentController::class, 'requestVoidPayment']);
        Route::post('rent-to-rent/payments/{payment}/approve-void', [RentToRentController::class, 'approveVoidPayment']);
        Route::post('rent-to-rent/payments/{payment}/reject-void', [RentToRentController::class, 'rejectVoidPayment']);
        Route::get('rent-to-rent/bills', [RentToRentController::class, 'bills']);
        Route::post('rent-to-rent/bills', [RentToRentController::class, 'generateBill']);
        Route::get('rent-to-rent/bills/{bill}', [RentToRentController::class, 'showBill']);
        Route::get('rent-to-rent/bills/{bill}/pdf', [RentToRentController::class, 'billPdf']);
        Route::post('rent-to-rent/bills/{bill}/mark-paid', [RentToRentController::class, 'markBillPaid']);
        Route::post('rent-to-rent/bills/{bill}/request-void', [RentToRentController::class, 'requestVoid']);
        Route::post('rent-to-rent/bills/{bill}/approve-void', [RentToRentController::class, 'approveVoid']);
        Route::post('rent-to-rent/bills/{bill}/reject-void', [RentToRentController::class, 'rejectVoid']);
        Route::get('rent-to-rent/{debt}', [RentToRentController::class, 'show']);
        Route::patch('rent-to-rent/{debt}/amount', [RentToRentController::class, 'updateAmount']);
        Route::post('rent-to-rent/{debt}/mark-paid', [RentToRentController::class, 'markDebtPaid']);
        Route::post('rent-to-rent/{debt}/payments', [RentToRentController::class, 'storeDebtPayment']);
        Route::post('rent-to-rent/bills/{bill}/mark-sent', [RentToRentController::class, 'markSent']);
        Route::post('rent-to-rent/bills/{bill}/payments', [RentToRentController::class, 'storePayment']);

        // Supervisor approval inbox
        Route::get('supervisor-requests', [SupervisorRequestController::class, 'index']);

        // Driver operational funds
        Route::get('operational-funds/bookings', [DriverOperationalFundController::class, 'bookings']);
        Route::get('operational-funds/history', [DriverOperationalFundController::class, 'history']);
        Route::get('operational-funds/{fund}', [DriverOperationalFundController::class, 'show']);
        Route::post('bookings/{booking}/operational-funds', [DriverOperationalFundController::class, 'store']);
        Route::post('operational-funds/{fund}/close', [DriverOperationalFundController::class, 'close']);
        Route::post('operational-funds/{fund}/accept', [DriverOperationalFundController::class, 'accept']);
        Route::post('operational-funds/{fund}/expenses', [DriverOperationalFundController::class, 'storeExpense']);
        Route::get('operational-expenses/{expense}/photo', [DriverOperationalFundController::class, 'showExpensePhoto']);
        Route::post('operational-expenses/{expense}/approve', [DriverOperationalFundController::class, 'approveExpense']);
        Route::post('operational-expenses/{expense}/reject', [DriverOperationalFundController::class, 'rejectExpense']);
        Route::get('driver/operational-funds', [DriverOperationalFundController::class, 'driverFunds']);
        Route::get('driver/schedules', [DriverOperationalFundController::class, 'driverSchedules']);

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
