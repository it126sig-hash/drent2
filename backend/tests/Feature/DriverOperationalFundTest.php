<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Customer;
use App\Models\Booking;
use App\Models\DriverOperationalFund;
use App\Models\DriverOperationalExpense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverOperationalFundTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_authorize_review_policy_with_class_name(): void
    {
        $tenant = Tenant::create(['name' => 'DRENT', 'slug' => uniqid('drent-'), 'is_active' => true]);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main']);
        
        $financeUser = User::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Finance Staff',
            'email' => 'finance@example.test',
            'password' => 'password',
            'role' => 'finance',
            'is_active' => true,
        ]);

        Sanctum::actingAs($financeUser);

        // This is the core call that failed with "Too few arguments" in the policy
        $this->assertTrue(Gate::allows('review', DriverOperationalFund::class));
    }

    public function test_can_authorize_review_policy_with_null_fund_in_void_expense(): void
    {
        $tenant = Tenant::create(['name' => 'DRENT', 'slug' => uniqid('drent-'), 'is_active' => true]);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main']);
        
        $financeUser = User::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Finance Staff',
            'email' => 'finance@example.test',
            'password' => 'password',
            'role' => 'finance',
            'is_active' => true,
        ]);

        Sanctum::actingAs($financeUser);

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
            'kode_booking' => 'BK-TEST-VOID',
            'status' => 'waiting_list',
            'lama_sewa' => 1,
            'paket_sewa' => 'harian',
            'tujuan' => 'Bandung',
        ]);

        // Create an operational expense without a fund (fund is null)
        $expense = DriverOperationalExpense::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'driver_operational_fund_id' => null,
            'booking_id' => $booking->id,
            'driver_id' => null,
            'cost_type_id' => null,
            'type' => 'realisasi',
            'amount' => 50000,
            'description' => 'Test expense without fund',
            'status' => 'pending',
            'source' => 'driver',
        ]);

        // Attempting to authorize using the fallback to class name
        $target = $expense->fund ?? DriverOperationalFund::class;
        $this->assertEquals(DriverOperationalFund::class, $target);

        $this->assertTrue(Gate::allows('review', $target));
    }

    public function test_void_expense_approval_flow(): void
    {
        $tenant = Tenant::create(['name' => 'DRENT', 'slug' => uniqid('drent-'), 'is_active' => true]);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main']);
        
        $financeUser = User::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Finance Staff',
            'email' => 'finance@example.test',
            'password' => 'password',
            'role' => 'finance',
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
            'kode_booking' => 'BK-TEST-VOID-2',
            'status' => 'waiting_list',
            'lama_sewa' => 1,
            'paket_sewa' => 'harian',
            'tujuan' => 'Bandung',
        ]);

        $driver = \App\Models\Driver::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'nama' => 'Driver Test',
            'saldo' => 50000,
            'status' => 'Aktif',
            'kontak_1' => '0812345678',
        ]);

        $paymentAccount = \App\Models\PaymentAccount::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'nama_bank' => 'BCA',
            'nomor_rekening' => '12345678',
            'atas_nama' => 'DRENT',
            'saldo' => 10000000,
        ]);

        $fund = DriverOperationalFund::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'booking_id' => $booking->id,
            'driver_id' => $driver->id,
            'payment_account_id' => $paymentAccount->id,
            'fund_type' => 'operational',
            'amount' => 100000,
            'paid_at' => now(),
            'recipient_destination' => 'Test destination',
            'status' => 'accepted',
            'created_by' => $financeUser->id,
        ]);

        $expense = DriverOperationalExpense::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'driver_operational_fund_id' => $fund->id,
            'booking_id' => $booking->id,
            'driver_id' => $driver->id,
            'cost_type_id' => null,
            'type' => 'expense',
            'amount' => 20000,
            'description' => 'Test expense description',
            'status' => 'approved',
            'source' => 'finance',
            'submitted_by' => $financeUser->id,
        ]);

        // Scenario 1: Request void
        Sanctum::actingAs($financeUser);
        $response = $this->postJson("/api/v1/operational-expenses/{$expense->id}/void", [
            'void_reason' => 'Salah input nominal',
        ]);

        $response->assertStatus(200);
        $expense->refresh();
        $this->assertEquals('void_requested', $expense->status);
        $this->assertEquals('Salah input nominal', $expense->void_reason);
        $this->assertEquals($financeUser->id, $expense->void_requested_by);

        // Driver balance should NOT have changed yet (still 50000)
        $driver->refresh();
        $this->assertEquals(50000, $driver->saldo);

        // Scenario 2: Supervisor rejects void request
        Sanctum::actingAs($supervisorUser);
        $responseReject = $this->postJson("/api/v1/operational-expenses/{$expense->id}/reject-void", [
            'rejection_note' => 'Dokumen pendukung valid',
        ]);

        $responseReject->assertStatus(200);
        $expense->refresh();
        $this->assertEquals('approved', $expense->status);
        $this->assertEquals('Dokumen pendukung valid', $expense->void_rejection_note);
        $this->assertEquals($supervisorUser->id, $expense->void_rejected_by);
        $driver->refresh();
        $this->assertEquals(50000, $driver->saldo);

        // Scenario 3: Request void again and approve it
        Sanctum::actingAs($financeUser);
        $responseAgain = $this->postJson("/api/v1/operational-expenses/{$expense->id}/void", [
            'void_reason' => 'Salah input nominal 2',
        ]);
        $responseAgain->assertStatus(200);

        Sanctum::actingAs($supervisorUser);
        $responseApprove = $this->postJson("/api/v1/operational-expenses/{$expense->id}/approve-void");
        $responseApprove->assertStatus(200);

        $expense->refresh();
        $this->assertEquals('rejected', $expense->status);
        $this->assertTrue(str_contains($expense->rejection_reason, '[VOID] Salah input nominal 2'));
        $this->assertEquals($supervisorUser->id, $expense->void_approved_by);

        // Driver balance should have changed (refunded 20000, so 50000 + 20000 = 70000)
        $driver->refresh();
        $this->assertEquals(70000, $driver->saldo);
    }
}
