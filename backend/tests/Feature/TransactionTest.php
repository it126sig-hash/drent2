<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingPayment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DriverOperationalExpense;
use App\Models\PaymentAccount;
use App\Models\RentalOwner;
use App\Models\RentToRentDebt;
use App\Models\RentToRentPayment;
use App\Models\RentToRentPaymentAllocation;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_transactions_require_auth_and_correct_role(): void
    {
        $this->getJson('/api/v1/transactions')->assertUnauthorized();

        // Admin branch can access
        $ctx = $this->context('admin_branch');
        $this->getJson('/api/v1/transactions')->assertOk();

        // CS role can NOT access
        $this->context('cs');
        $this->getJson('/api/v1/transactions')->assertForbidden();
    }

    public function test_transactions_scope_branch_and_tenant(): void
    {
        $ctxA = $this->context('admin_branch');
        $branchB = Branch::create(['tenant_id' => $ctxA['tenant']->id, 'name' => 'Branch B']);
        
        $customer = $this->customer($ctxA, 'Normal', 'Maya');
        $owner = $this->owner($ctxA['tenant']->id, true, 'Internal');
        $unitA = $this->unit($ctxA, $owner->id);

        // Booking in branch A
        $this->booking($ctxA, $customer, $unitA, ['status' => 'waiting_list']);

        // Booking in branch B
        $this->booking(
            array_merge($ctxA, ['branch' => $branchB]),
            $customer,
            $unitA,
            ['branch_id' => $branchB->id, 'status' => 'waiting_list']
        );

        // Fetch as branch A admin
        $this->getJson('/api/v1/transactions?status=waiting_list')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Fetch as superadmin (sees both)
        $superadmin = User::create([
            'tenant_id' => $ctxA['tenant']->id,
            'name' => 'Superadmin',
            'email' => uniqid('superadmin-').'@example.test',
            'password' => 'password',
            'role' => 'superadmin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($superadmin);

        $this->getJson('/api/v1/transactions?status=waiting_list')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_transactions_returns_expected_fields_and_computations(): void
    {
        $ctx = $this->context('admin_branch');
        $account = $this->paymentAccount($ctx);
        $customer = $this->customer($ctx, 'Member', 'Budi Member');
        
        // Rent-to-rent unit (Mitra owner)
        $mitraOwner = $this->owner($ctx['tenant']->id, false, 'Mitra');
        $mitraUnit = $this->unit($ctx, $mitraOwner->id);

        $booking = $this->booking($ctx, $customer, $mitraUnit, [
            'status' => 'selesai',
            'harga_dealing' => 300000,
        ]);

        // Add booking payment (Income)
        BookingPayment::create([
            'booking_id' => $booking->id,
            'payment_account_id' => $account->id,
            'amount' => 300000,
            'payment_type' => 'dp',
            'paid_at' => now(),
            'created_by' => $ctx['user']->id,
        ]);

        // Add rent to rent debt
        $debt = RentToRentDebt::create([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'rental_owner_id' => $mitraOwner->id,
            'booking_id' => $booking->id,
            'booking_detail_id' => $booking->bookingDetails->first()->id,
            'status' => 'open',
            'cached_total_amount' => 100000,
            'cached_paid_amount' => 0,
            'cached_payment_status' => 'open',
        ]);

        // Add operational expense
        DriverOperationalExpense::create([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'booking_id' => $booking->id,
            'booking_detail_id' => $booking->bookingDetails->first()->id,
            'type' => 'expense',
            'amount' => 50000,
            'description' => 'Bensin Tol',
            'status' => 'approved',
            'submitted_by' => $ctx['user']->id,
        ]);

        // Fetch list
        $response = $this->getJson('/api/v1/transactions?status=selesai')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response->assertJsonPath('data.0.kode_booking', $booking->kode_booking)
            ->assertJsonPath('data.0.total_biaya', 300000)
            ->assertJsonPath('data.0.total_rent_to_rent', 100000)
            ->assertJsonPath('data.0.total_operasional', 50000)
            ->assertJsonPath('data.0.total_pengeluaran', 150000)
            ->assertJsonPath('data.0.margin', 150000);
    }

    public function test_show_detail_returns_unified_history_properly(): void
    {
        $ctx = $this->context('admin_branch');
        $account = $this->paymentAccount($ctx);
        $customer = $this->customer($ctx, 'Member', 'Budi Member');
        $mitraOwner = $this->owner($ctx['tenant']->id, false, 'Mitra');
        $mitraUnit = $this->unit($ctx, $mitraOwner->id);

        $booking = $this->booking($ctx, $customer, $mitraUnit, [
            'status' => 'selesai',
        ]);

        // 1. Income payment
        BookingPayment::create([
            'booking_id' => $booking->id,
            'payment_account_id' => $account->id,
            'amount' => 300000,
            'payment_type' => 'dp',
            'paid_at' => now(),
            'created_by' => $ctx['user']->id,
        ]);

        // 2. Operational expense
        DriverOperationalExpense::create([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'booking_id' => $booking->id,
            'booking_detail_id' => $booking->bookingDetails->first()->id,
            'type' => 'expense',
            'amount' => 50000,
            'description' => 'Bensin Tol',
            'status' => 'approved',
            'submitted_by' => $ctx['user']->id,
        ]);

        // 3. Rent to Rent payment
        $debt = RentToRentDebt::create([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'rental_owner_id' => $mitraOwner->id,
            'booking_id' => $booking->id,
            'booking_detail_id' => $booking->bookingDetails->first()->id,
            'status' => 'open',
            'cached_total_amount' => 100000,
        ]);

        $rtrPayment = RentToRentPayment::create([
            'payment_account_id' => $account->id,
            'amount' => 100000,
            'status' => 'active',
            'paid_at' => now(),
            'created_by' => $ctx['user']->id,
        ]);

        RentToRentPaymentAllocation::create([
            'rent_to_rent_payment_id' => $rtrPayment->id,
            'rent_to_rent_debt_id' => $debt->id,
            'amount' => 100000,
        ]);

        // Fetch detail
        $response = $this->getJson("/api/v1/transactions/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.kode_booking', $booking->kode_booking)
            ->assertJsonCount(3, 'data.history');

        // Assert history contains income, operational, and rent-to-rent payments
        $history = $response->json('data.history');
        
        $categories = collect($history)->pluck('category')->toArray();
        $this->assertContains('pembayaran_booking', $categories);
        $this->assertContains('operasional', $categories);
        $this->assertContains('rent_to_rent', $categories);

        // Check summaries
        $response->assertJsonPath('data.summary.total_pemasukan', 300000)
            ->assertJsonPath('data.summary.total_pengeluaran', 150000)
            ->assertJsonPath('data.summary.margin', 150000);
    }

    private function context(string $role): array
    {
        $tenant = Tenant::create(['name' => 'DRENT', 'slug' => uniqid('drent-'), 'is_active' => true]);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $role === 'superadmin' ? null : $branch->id,
            'name' => ucfirst($role),
            'email' => uniqid($role.'-').'@example.test',
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);

        if (in_array($role, ['admin_branch', 'finance'], true)) {
            \App\Models\RolePermission::create([
                'tenant_id' => $tenant->id,
                'role' => $role,
                'permission_key' => 'finance.transaction',
            ]);
        }

        Sanctum::actingAs($user);

        return compact('tenant', 'branch', 'user');
    }

    private function customer(array $ctx, string $status, string $name): Customer
    {
        return Customer::create([
            'tenant_id' => $ctx['tenant']->id,
            'nama' => $name,
            'kontak_1' => uniqid('08'),
            'kota' => 'Jakarta',
            'status' => $status,
        ]);
    }

    private function owner(int $tenantId, bool $isOwner, string $name): RentalOwner
    {
        return RentalOwner::create([
            'tenant_id' => $tenantId,
            'nama' => $name,
            'kontak_1' => uniqid('08'),
            'is_owner' => $isOwner,
        ]);
    }

    private function unit(array $ctx, int $ownerId): Unit
    {
        return Unit::create([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'rental_owner_id' => $ownerId,
            'tipe' => 'Avanza',
            'merk' => 'Toyota',
            'tahun' => 2024,
            'no_polisi' => uniqid('B'),
            'harga_1_hari' => 300000,
            'harga_1_minggu' => 1800000,
            'harga_1_bulan' => 6000000,
            'modal_1_hari' => 100000,
            'modal_1_minggu' => 700000,
            'modal_1_bulan' => 2500000,
            'status' => 'Aktif',
        ]);
    }

    private function paymentAccount(array $ctx): PaymentAccount
    {
        return PaymentAccount::create([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'nama_bank' => 'BCA',
            'nomor_rekening' => uniqid('123'),
            'atas_nama' => 'DRENT',
            'is_active' => true,
        ]);
    }

    private function booking(array $ctx, Customer $customer, Unit $unit, array $overrides = []): Booking
    {
        $booking = Booking::create(array_merge([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'customer_id' => $customer->id,
            'created_by' => $ctx['user']->id,
            'kode_booking' => uniqid('BK-'),
            'status' => 'waiting_list',
            'harga_dealing' => 300000,
            'lama_sewa' => 1,
            'paket_sewa' => 'harian',
            'tujuan' => 'Bandung',
            'kota' => 'Bandung',
        ], $overrides));

        BookingDetail::create([
            'booking_id' => $booking->id,
            'unit_id' => $unit->id,
            'tgl_sewa' => '2026-05-16 07:00:00',
            'tgl_kembali' => '2026-05-17 23:59:00',
            'harga_mobil' => 300000,
            'diskon_mobil' => 0,
            'lama_sewa' => 1,
            'paket_sewa' => 'harian',
            'pricing_mode' => 'non_all_in',
            'detail_type' => 'initial',
            'status' => 'aktif',
        ]);

        return $booking->load('bookingDetails');
    }
}
