<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReceivableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoicePaymentVoidTest extends TestCase
{
    use RefreshDatabase;

    public function test_void_invoice_payment_approval_flow(): void
    {
        $tenant = Tenant::create(['name' => 'DRENT', 'slug' => uniqid('drent-'), 'is_active' => true]);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main']);

        $financeUser = User::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Finance Staff',
            'email' => 'finance@example.test',
            'password' => 'password',
            'role' => 'superadmin',
            'is_active' => true,
        ]);

        $supervisorUser = User::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Supervisor Staff',
            'email' => 'supervisor@example.test',
            'password' => 'password',
            'role' => 'supervisor',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'nama' => 'Test Customer',
            'kontak_1' => '08123456789',
            'kota' => 'Jakarta',
            'status' => 'Normal',
        ]);

        $booking = Booking::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by' => $financeUser->id,
            'kode_booking' => 'BK-TEST-INV-VOID',
            'status' => 'confirm',
            'lama_sewa' => 1,
            'paket_sewa' => 'harian',
            'tujuan' => 'Bandung',
        ]);

        $paymentAccount = PaymentAccount::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'nama_bank' => 'BCA',
            'nomor_rekening' => '12345678',
            'atas_nama' => 'DRENT',
            'saldo' => 10000000,
            'current_balance' => 10000000,
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'invoice_number' => 'INV-TEST-0001',
            'public_token' => 'test-token-0001',
            'status' => 'generated',
            'total_amount' => 100000,
            'paid_amount' => 0,
            'due_date' => now()->addDays(3),
            'generated_at' => now(),
            'created_by' => $financeUser->id,
        ]);
        $invoice->bookings()->attach($booking->id, ['amount' => 100000]);

        $receivableService = app(ReceivableService::class);

        Sanctum::actingAs($financeUser);
        $invoice = $receivableService->storePayment($invoice, [
            'payment_account_id' => $paymentAccount->id,
            'amount' => 100000,
        ]);

        $payment = $invoice->payments->first();
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(100000, $invoice->paid_amount);
        $paymentAccount->refresh();
        $this->assertEquals(10100000, $paymentAccount->current_balance);

        $childBookingPayment = BookingPayment::where('invoice_payment_id', $payment->id)->first();
        $this->assertNotNull($childBookingPayment);
        $this->assertEquals('active', $childBookingPayment->status);

        // Request void
        $response = $this->postJson("/api/v1/invoice-payments/{$payment->id}/request-void", [
            'void_reason' => 'Salah input nominal pembayaran',
        ]);
        $response->assertStatus(200);
        $payment->refresh();
        $this->assertEquals('void_requested', $payment->status);

        // Reject by supervisor
        Sanctum::actingAs($supervisorUser);
        $reject = $this->postJson("/api/v1/invoice-payments/{$payment->id}/reject-void", [
            'void_rejection_note' => 'Dokumen valid',
        ]);
        $reject->assertStatus(200);
        $payment->refresh();
        $this->assertEquals('active', $payment->status);

        // Request void again, then approve
        Sanctum::actingAs($financeUser);
        $this->postJson("/api/v1/invoice-payments/{$payment->id}/request-void", [
            'void_reason' => 'Salah input nominal pembayaran lagi',
        ])->assertStatus(200);

        Sanctum::actingAs($supervisorUser);
        $approve = $this->postJson("/api/v1/invoice-payments/{$payment->id}/approve-void");
        $approve->assertStatus(200);

        $payment->refresh();
        $this->assertEquals('voided', $payment->status);

        $invoice->refresh();
        $this->assertEquals('generated', $invoice->status);
        $this->assertEquals(0, $invoice->paid_amount);

        $childBookingPayment->refresh();
        $this->assertEquals('voided', $childBookingPayment->status);

        $paymentAccount->refresh();
        // Balance kembali ke kondisi sebelum pembayaran — tanpa baris transaksi reversal baru selain delta void
        $this->assertEquals(10000000, $paymentAccount->current_balance);

        // Public invoice tidak menampilkan riwayat pembayaran yang sudah void
        $history = $receivableService->invoicePaymentHistory($invoice->fresh(['bookings.payments', 'payments']));
        $this->assertCount(0, $history);
    }
}
