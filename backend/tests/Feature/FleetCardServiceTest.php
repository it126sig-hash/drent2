<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\FleetCard;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FleetCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FleetCardServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Saldo kartu fleet harus persist lintas booking: sisa saldo dari fund yang sudah
     * di-close harus otomatis tersedia sebagai "carried over balance" saat kartu yang
     * sama dipasangkan ke booking_detail lain dengan driver yang berbeda.
     */
    public function test_fleet_card_balance_carries_over_across_bookings(): void
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

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'nama' => 'Test Customer',
            'kontak_1' => '08123456789',
            'kota' => 'Jakarta',
            'status' => 'Normal',
        ]);

        $driverA = Driver::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'nama' => 'Driver A',
            'saldo' => 0,
            'status' => 'Aktif',
            'kontak_1' => '0812345678',
        ]);

        $driverB = Driver::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'nama' => 'Driver B',
            'saldo' => 0,
            'status' => 'Aktif',
            'kontak_1' => '0812345679',
        ]);

        $paymentAccount = PaymentAccount::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'nama_bank' => 'BCA',
            'nomor_rekening' => '12345678',
            'atas_nama' => 'DRENT',
            'saldo' => 10_000_000,
        ]);

        $bookingA = Booking::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by' => $financeUser->id,
            'kode_booking' => 'BK-FLEET-A',
            'status' => 'rental_unit',
            'lama_sewa' => 1,
            'paket_sewa' => 'harian',
            'tujuan' => 'Bandung',
        ]);

        $detailA = BookingDetail::create([
            'booking_id' => $bookingA->id,
            'driver_id' => $driverA->id,
            'tgl_sewa' => now(),
            'tgl_kembali' => now()->addDay(),
            'lama_sewa' => 1,
            'paket_sewa' => 'harian',
            'pricing_mode' => 'all_in',
            'harga_all_in' => 500_000,
            'detail_type' => 'initial',
            'status' => 'aktif',
            'created_by' => $financeUser->id,
        ]);

        $bookingB = Booking::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by' => $financeUser->id,
            'kode_booking' => 'BK-FLEET-B',
            'status' => 'rental_unit',
            'lama_sewa' => 1,
            'paket_sewa' => 'harian',
            'tujuan' => 'Jakarta',
        ]);

        $detailB = BookingDetail::create([
            'booking_id' => $bookingB->id,
            'driver_id' => $driverB->id,
            'tgl_sewa' => now()->addDays(2),
            'tgl_kembali' => now()->addDays(3),
            'lama_sewa' => 1,
            'paket_sewa' => 'harian',
            'pricing_mode' => 'all_in',
            'harga_all_in' => 500_000,
            'detail_type' => 'initial',
            'status' => 'aktif',
            'created_by' => $financeUser->id,
        ]);

        $card = FleetCard::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'code' => 'FC-001',
            'status' => 'active',
            'created_by' => $financeUser->id,
        ]);

        Sanctum::actingAs($financeUser);
        $service = app(FleetCardService::class);

        // Round 1: top-up 500.000 untuk booking A, driver A.
        $fund1 = $service->createFund($detailA, [
            'fleet_card_id' => $card->id,
            'driver_id' => $driverA->id,
            'payment_account_id' => $paymentAccount->id,
            'amount' => 500_000,
            'paid_at' => now()->toDateString(),
            'recipient_destination' => 'Driver A',
            'items' => [
                ['label' => 'BBM', 'planned_amount' => 500_000],
            ],
        ]);

        $card->refresh();
        $this->assertEquals(500_000, $card->saldo);
        $this->assertEquals('accepted', $fund1->status); // driver tanpa user login -> auto accepted

        // Driver A pakai 200.000, sisanya 300.000 harus tetap nempel di kartu setelah ditutup.
        $expense1 = $service->createExpense($fund1, [
            'type' => 'expense',
            'amount' => 200_000,
            'description' => 'Isi BBM',
        ]);
        $this->assertEquals('approved', $expense1->status); // finance source -> auto approved

        $card->refresh();
        $this->assertEquals(300_000, $card->saldo);

        $service->closeFund($fund1, 'Selesai sewa A');

        // Round 2: kartu yang SAMA dipasang ke booking B dengan driver B, tanpa top-up baru.
        // Breakdown wajib total 300.000 (saldo sisa kartu), bukan 0.
        $fund2 = $service->createFund($detailB, [
            'fleet_card_id' => $card->id,
            'driver_id' => $driverB->id,
            'amount' => 0,
            'recipient_destination' => 'Driver B',
            'items' => [
                ['label' => 'Sisa BBM', 'planned_amount' => 300_000],
            ],
        ]);

        $this->assertEquals('accepted', $fund2->status);
        $card->refresh();
        $this->assertEquals(300_000, $card->saldo, 'Saldo kartu harus tetap 300.000 (carried over), bukan ter-reset ke 0.');

        $expense2 = $service->createExpense($fund2, [
            'type' => 'expense',
            'amount' => 300_000,
            'description' => 'Habiskan sisa BBM',
        ]);
        $this->assertEquals('approved', $expense2->status);

        $card->refresh();
        $this->assertEquals(0, $card->saldo);

        $service->closeFund($fund2, 'Selesai sewa B');
        $this->assertEquals('closed', $fund2->fresh()->status);
    }

    public function test_create_fund_rejects_mismatched_item_breakdown(): void
    {
        $tenant = Tenant::create(['name' => 'DRENT', 'slug' => uniqid('drent-'), 'is_active' => true]);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main']);

        $financeUser = User::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Finance Staff',
            'email' => 'finance2@example.test',
            'password' => 'password',
            'role' => 'finance',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'nama' => 'Test Customer',
            'kontak_1' => '08123456789',
            'kota' => 'Jakarta',
            'status' => 'Normal',
        ]);

        $driver = Driver::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'nama' => 'Driver A',
            'saldo' => 0,
            'status' => 'Aktif',
            'kontak_1' => '0812345678',
        ]);

        $paymentAccount = PaymentAccount::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'nama_bank' => 'BCA',
            'nomor_rekening' => '12345678',
            'atas_nama' => 'DRENT',
            'saldo' => 10_000_000,
        ]);

        $booking = Booking::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by' => $financeUser->id,
            'kode_booking' => 'BK-FLEET-C',
            'status' => 'rental_unit',
            'lama_sewa' => 1,
            'paket_sewa' => 'harian',
            'tujuan' => 'Bandung',
        ]);

        $detail = BookingDetail::create([
            'booking_id' => $booking->id,
            'driver_id' => $driver->id,
            'tgl_sewa' => now(),
            'tgl_kembali' => now()->addDay(),
            'lama_sewa' => 1,
            'paket_sewa' => 'harian',
            'pricing_mode' => 'all_in',
            'harga_all_in' => 500_000,
            'detail_type' => 'initial',
            'status' => 'aktif',
            'created_by' => $financeUser->id,
        ]);

        $card = FleetCard::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'code' => 'FC-002',
            'status' => 'active',
            'created_by' => $financeUser->id,
        ]);

        Sanctum::actingAs($financeUser);
        $service = app(FleetCardService::class);

        $this->expectException(\InvalidArgumentException::class);

        $service->createFund($detail, [
            'fleet_card_id' => $card->id,
            'driver_id' => $driver->id,
            'payment_account_id' => $paymentAccount->id,
            'amount' => 500_000,
            'recipient_destination' => 'Driver A',
            'items' => [
                ['label' => 'BBM', 'planned_amount' => 100_000], // mismatch: harus 500_000
            ],
        ]);
    }
}
