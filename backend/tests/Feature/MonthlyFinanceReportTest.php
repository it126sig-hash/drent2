<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingPayment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\FinanceCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MonthlyFinanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_and_other_transactions_update_balances_and_monthly_report(): void
    {
        $ctx = $this->context('supervisor');
        $source = $this->paymentAccount($ctx, 'BCA', 1000000);
        $target = $this->paymentAccount($ctx, 'Mandiri', 100000);
        $incomeCategory = $this->category($ctx, 'Setoran Owner', 'income');
        $expenseCategory = $this->category($ctx, 'ATK Kantor', 'expense');

        $this->postJson('/api/v1/payment-account-transactions/transfer', [
            'from_payment_account_id' => $source->id,
            'to_payment_account_id' => $target->id,
            'amount' => 250000,
            'transaction_at' => '2026-05-05 10:00:00',
            'description' => 'Pindah dana operasional',
        ])->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'transfer_out')
            ->assertJsonPath('data.1.type', 'transfer_in');

        $this->postJson('/api/v1/payment-account-transactions/other', [
            'payment_account_id' => $source->id,
            'finance_category_id' => $incomeCategory->id,
            'type' => 'income',
            'amount' => 100000,
            'transaction_at' => '2026-05-06 10:00:00',
            'description' => 'Pemasukan non rental',
        ])->assertOk()
            ->assertJsonPath('data.type', 'other_income');

        $this->postJson('/api/v1/payment-account-transactions/other', [
            'payment_account_id' => $target->id,
            'finance_category_id' => $expenseCategory->id,
            'type' => 'expense',
            'amount' => 40000,
            'transaction_at' => '2026-05-07 10:00:00',
            'description' => 'Beli ATK',
        ])->assertOk()
            ->assertJsonPath('data.type', 'other_expense');

        $this->assertSame(850000, $source->fresh()->current_balance);
        $this->assertSame(310000, $target->fresh()->current_balance);

        $response = $this->getJson('/api/v1/reports/monthly-finance?month=5&year=2026')
            ->assertOk();

        $response->assertJsonPath('data.summary.other_income', 100000)
            ->assertJsonPath('data.summary.business_expense', 40000)
            ->assertJsonPath('data.summary.net_cash', 60000)
            ->assertJsonPath('data.summary.transfer_in', 250000)
            ->assertJsonPath('data.summary.transfer_out', 250000);
    }

    public function test_monthly_report_counts_invoice_payment_once_and_excludes_voided_booking_payment(): void
    {
        $ctx = $this->context('finance');
        $account = $this->paymentAccount($ctx, 'BCA', 0);
        $customer = Customer::create([
            'tenant_id' => $ctx['tenant']->id,
            'nama' => 'Budi',
            'kontak_1' => '08123',
            'kota' => 'Jakarta',
            'status' => 'Normal',
        ]);
        $booking = $this->booking($ctx, $customer);
        $invoice = Invoice::create([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'invoice_number' => 'INV-TEST-001',
            'status' => 'partial_paid',
            'total_amount' => 1000000,
            'paid_amount' => 300000,
            'generated_at' => '2026-05-04 10:00:00',
            'created_by' => $ctx['user']->id,
        ]);
        $invoice->bookings()->attach($booking->id, ['amount' => 1000000]);
        // Invoice payment: 300k (invoice_payment_in).
        $invoicePayment = app(\App\Services\ReceivableService::class)->storePayment($invoice, [
            'payment_account_id' => $account->id,
            'amount' => 300000,
            'paid_at' => '2026-05-05 10:00:00',
        ]);

        // Booking payments: DP 100k + cicilan 200k yang di-void → sisa aktif 100k.
        // (Pembayaran invoice TIDAK diduplikasi sebagai booking payment,
        //  karena itu akan menghitung uang yang sama dua kali di laporan.)
        app(\App\Services\BookingPaymentService::class)->create($booking, [
            'payment_account_id' => $account->id,
            'amount' => 100000,
            'payment_type' => 'dp',
            'status' => 'active',
            'paid_at' => '2026-05-03 10:00:00',
        ]);
        $voided = app(\App\Services\BookingPaymentService::class)->create($booking, [
            'payment_account_id' => $account->id,
            'amount' => 200000,
            'payment_type' => 'cicilan',
            'status' => 'active',
            'paid_at' => '2026-05-06 10:00:00',
        ]);
        app(\App\Services\BookingPaymentService::class)->requestVoid($voided, 'salah input');

        // ACC harus oleh user lain (superadmin bypass aturan "supervisor lain").
        $supervisor = User::create([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'name' => 'Super',
            'email' => uniqid('super-').'@example.test',
            'password' => 'password',
            'role' => 'superadmin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($supervisor);
        // Void reversal tx memakai transaction_at = now(); bekukan waktu agar tetap di periode Mei.
        Carbon::setTestNow('2026-05-07 09:00:00');
        app(\App\Services\BookingPaymentService::class)->approveVoid($voided);
        Carbon::setTestNow();
        Sanctum::actingAs($ctx['user']);

        $this->getJson('/api/v1/reports/monthly-finance?month=5&year=2026')
            ->assertOk()
            ->assertJsonPath('data.summary.booking_revenue', 1000000)
            ->assertJsonPath('data.summary.rental_income', 400000);
        // rental_income = invoice_payment_in 300k + booking_payment_in 100k
        // − booking_payment_void 100k (cicilan di-void) + 100k DP... = 400k aktif
        // (invoice 300k + booking aktif 100k = 400k; void 100k masuk lalu dikeluarkan)
    }

    private function context(string $role): array
    {
        $tenant = Tenant::create(['name' => 'DRENT', 'slug' => uniqid('drent-'), 'is_active' => true]);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => ucfirst($role),
            'email' => uniqid($role.'-').'@example.test',
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);

        // Report access is gated by finance.account_mutation (PaymentAccountTransactionPolicy::viewAny).
        \App\Models\RolePermission::create([
            'tenant_id' => $tenant->id,
            'role' => $role,
            'permission_key' => 'finance.account_mutation',
        ]);

        Sanctum::actingAs($user);

        return compact('tenant', 'branch', 'user');
    }

    private function paymentAccount(array $ctx, string $bank, int $balance): PaymentAccount
    {
        return PaymentAccount::create([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'nama_bank' => $bank,
            'nomor_rekening' => uniqid('123'),
            'atas_nama' => 'DRENT',
            'current_balance' => $balance,
            'is_active' => true,
        ]);
    }

    private function category(array $ctx, string $name, string $type): FinanceCategory
    {
        return FinanceCategory::create([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'name' => $name,
            'type' => $type,
            'is_active' => true,
        ]);
    }

    private function booking(array $ctx, Customer $customer): Booking
    {
        $booking = Booking::create([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'customer_id' => $customer->id,
            'created_by' => $ctx['user']->id,
            'kode_booking' => uniqid('BK-'),
            'status' => 'selesai',
            'harga_dealing' => 1000000,
            'lama_sewa' => 1,
            'paket_sewa' => 'harian',
            'tujuan' => 'Bandung',
            'completed_at' => '2026-05-10 10:00:00',
            'updated_at' => '2026-05-10 10:00:00',
        ]);

        BookingDetail::create([
            'booking_id' => $booking->id,
            'unit_placeholder' => 'Unit Test',
            'tgl_sewa' => '2026-05-09 07:00:00',
            'tgl_kembali' => '2026-05-10 23:59:00',
            'harga_mobil' => 1000000,
            'diskon_mobil' => 0,
            'lama_sewa' => 1,
            'paket_sewa' => 'harian',
            'pricing_mode' => 'non_all_in',
            'detail_type' => 'initial',
            'status' => 'selesai',
        ]);

        return $booking->fresh(['bookingDetails.costs', 'payments']);
    }
}
