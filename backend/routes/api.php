<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\TenantController;
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
use App\Http\Controllers\Api\V1\ProvinceController;
use App\Http\Controllers\Api\V1\PricingPackageController;
use App\Http\Controllers\Api\V1\BookingPaymentController;
use App\Http\Controllers\Api\V1\RefundController;
use App\Http\Controllers\Api\V1\PhysicalCheckController;
use App\Http\Controllers\Api\V1\PhysicalCheckItemController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReceivableController;
use App\Http\Controllers\Api\V1\RentToRentController;
use App\Http\Controllers\Api\V1\SupervisorRequestController;
use App\Http\Controllers\Api\V1\MyRequestController;
use App\Http\Controllers\Api\V1\DriverOperationalFundController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FinanceCategoryController;
use App\Http\Controllers\Api\V1\MonthlyFinanceReportController;
use App\Http\Controllers\Api\V1\UnitUsageReportController;
use App\Http\Controllers\Api\V1\DriverUsageReportController;
use App\Http\Controllers\Api\V1\CustomerUsageReportController;
use App\Http\Controllers\Api\V1\PaymentAccountTransactionController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\InvoiceTermsTemplateController;
use App\Http\Controllers\Api\V1\BookingCancellationController;
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
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::post('/profile', [ProfileController::class, 'update']);
        Route::patch('/profile/password', [ProfileController::class, 'updatePassword']);
        Route::get('dashboard', [DashboardController::class, 'index']);
        
        Route::get('branches', [BranchController::class, 'index']);
        Route::get('branches/{branch}', [BranchController::class, 'show']);
        Route::post('branches', [BranchController::class, 'store']);
        // POST untuk update agar mendukung multipart/form-data (logo upload)
        Route::post('branches/{branch}', [BranchController::class, 'update']);
        Route::delete('branches/{branch}', [BranchController::class, 'destroy']);

        // Tenant (update-only — superadmin bisa kelola profil tenant miliknya)
        Route::get('tenant', [TenantController::class, 'show']);
        Route::post('tenant', [TenantController::class, 'update']);

        Route::apiResource('rental-owners', RentalOwnerController::class);
        
        // Users
        Route::get('roles', [UserController::class, 'roles']);
        Route::patch('users/{user}/reset-password', [UserController::class, 'resetPassword']);
        Route::apiResource('users', UserController::class);

        // Role Management
        Route::get('role-permissions', [\App\Http\Controllers\Api\V1\RolePermissionController::class, 'index']);
        Route::put('role-permissions/{role}', [\App\Http\Controllers\Api\V1\RolePermissionController::class, 'update']);
        Route::get('users/{user}/permissions', [\App\Http\Controllers\Api\V1\RolePermissionController::class, 'userPermissions']);
        Route::put('users/{user}/permissions', [\App\Http\Controllers\Api\V1\RolePermissionController::class, 'updateUserPermissions']);

        // Units
        Route::post('units/batch-update-city', [UnitController::class, 'batchUpdateCity']);
        Route::get('units/{unit}/schedule-check', [UnitController::class, 'checkSchedule']);
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
        Route::patch('members/{member}/status', [MemberController::class, 'updateStatus']);
        Route::post('members/{member}/extend', [MemberController::class, 'extend']);
        Route::get('members/{member}/extensions', [MemberController::class, 'extensions']);
        Route::get('members/{member}/documents/{type}', [MemberController::class, 'showDocument']);

        // Master Data: Payment Accounts, Cost Types, Pricing Packages
        Route::apiResource('payment-accounts', PaymentAccountController::class);
        Route::apiResource('finance-categories', FinanceCategoryController::class);
        Route::get('provinces', [ProvinceController::class, 'index']);
        Route::apiResource('cities', CityController::class);
        Route::apiResource('cost-types', CostTypeController::class);
        Route::get('pricing-packages/import-template', [PricingPackageController::class, 'importTemplate']);
        Route::post('pricing-packages/import', [PricingPackageController::class, 'import']);
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

        // Booking Cancellations
        Route::get('booking-cancellations', [BookingCancellationController::class, 'index']);
        Route::get('booking-cancellations/{bookingCancellation}', [BookingCancellationController::class, 'show']);
        Route::post('booking-cancellations/{bookingCancellation}/pay-refund', [BookingCancellationController::class, 'payRefund']);

        // Receivables & Invoices
        Route::get('reports/monthly-finance', MonthlyFinanceReportController::class);
        Route::get('reports/unit-usage', UnitUsageReportController::class);
        Route::get('reports/driver-usage', DriverUsageReportController::class);
        Route::get('reports/customer-usage', CustomerUsageReportController::class);
        Route::get('payment-account-transactions', [PaymentAccountTransactionController::class, 'index']);
        Route::post('payment-account-transactions/transfer', [PaymentAccountTransactionController::class, 'transfer']);
        Route::post('payment-account-transactions/other', [PaymentAccountTransactionController::class, 'other']);
        Route::post('payment-account-transactions/adjust', [PaymentAccountTransactionController::class, 'adjust']);
        Route::get('receivables', [ReceivableController::class, 'index']);
        Route::get('receivables/payment-history', [ReceivableController::class, 'paymentHistory']);
        Route::post('receivables/invoices', [ReceivableController::class, 'generateInvoice']);
        Route::get('invoices', [ReceivableController::class, 'invoices']);
        Route::get('invoices/{invoice}/pdf', [ReceivableController::class, 'invoicePdf']);
        Route::post('invoices/{invoice}/mark-sent', [ReceivableController::class, 'markInvoiceSent']);
        Route::post('invoices/{invoice}/refresh-amount', [ReceivableController::class, 'refreshInvoiceAmount']);
        Route::post('invoices/{invoice}/payments', [ReceivableController::class, 'storeInvoicePayment']);
        Route::get('invoices/{invoice}/histories', [ReceivableController::class, 'invoiceHistories']);
        Route::apiResource('invoice-terms-templates', InvoiceTermsTemplateController::class)->except(['show']);

        // Transactions
        Route::get('transactions', [TransactionController::class, 'index']);
        Route::get('transactions/{booking}', [TransactionController::class, 'show']);

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
        Route::post('rent-to-rent/debts/{debt}/amount-change-requests', [RentToRentController::class, 'requestAmountChange']);
        Route::post('rent-to-rent/amount-change-requests/{req}/approve', [RentToRentController::class, 'approveAmountChange']);
        Route::post('rent-to-rent/amount-change-requests/{req}/reject', [RentToRentController::class, 'rejectAmountChange']);
        Route::post('rent-to-rent/amount-change-requests/{req}/cancel', [RentToRentController::class, 'cancelAmountChange']);
        Route::post('rent-to-rent/{debt}/mark-paid', [RentToRentController::class, 'markDebtPaid']);
        Route::post('rent-to-rent/{debt}/payments', [RentToRentController::class, 'storeDebtPayment']);
        Route::post('rent-to-rent/bills/{bill}/mark-sent', [RentToRentController::class, 'markSent']);
        Route::post('rent-to-rent/bills/{bill}/payments', [RentToRentController::class, 'storePayment']);

        // Supervisor approval inbox
        Route::get('supervisor-requests', [SupervisorRequestController::class, 'index']);

        // Riwayat request perubahan milik user yang sedang login
        Route::get('my-requests', [MyRequestController::class, 'index']);

        // Driver operational funds
        Route::get('operational-funds/bookings', [DriverOperationalFundController::class, 'bookings']);
        Route::get('operational-funds/history', [DriverOperationalFundController::class, 'history']);
        Route::get('operational-funds/{fund}', [DriverOperationalFundController::class, 'show']);
        Route::post('bookings/{booking}/operational-funds', [DriverOperationalFundController::class, 'store']);
        Route::post('bookings/{booking}/expenses', [DriverOperationalFundController::class, 'storeBookingExpense']);
        Route::post('operational-funds/{fund}/close', [DriverOperationalFundController::class, 'close']);
        Route::post('operational-funds/{fund}/accept', [DriverOperationalFundController::class, 'accept']);
        Route::post('operational-funds/{fund}/expenses', [DriverOperationalFundController::class, 'storeExpense']);
        Route::post('operational-funds/{fund}/void', [DriverOperationalFundController::class, 'voidFund']);
        Route::get('operational-expenses/{expense}/photo', [DriverOperationalFundController::class, 'showExpensePhoto']);
        Route::post('operational-expenses/{expense}/approve', [DriverOperationalFundController::class, 'approveExpense']);
        Route::post('operational-expenses/{expense}/reject', [DriverOperationalFundController::class, 'rejectExpense']);
        Route::post('operational-expenses/{expense}/void', [DriverOperationalFundController::class, 'voidExpense']);
        Route::post('operational-expenses/{expense}/approve-void', [DriverOperationalFundController::class, 'approveVoidExpense']);
        Route::post('operational-expenses/{expense}/reject-void', [DriverOperationalFundController::class, 'rejectVoidExpense']);
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
        Route::post('bookings/{booking}/operational-complete', [DriverOperationalFundController::class, 'markOperationalComplete']);
        Route::post('bookings/{booking}/operational-revert', [DriverOperationalFundController::class, 'requestRevertOperational']);
        Route::post('bookings/{booking}/operational-revert/approve', [DriverOperationalFundController::class, 'approveRevertOperational']);
        Route::post('bookings/{booking}/operational-revert/reject', [DriverOperationalFundController::class, 'rejectRevertOperational']);
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
