<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\RentalOwner;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\BookingService;
use App\Services\ReceivableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceDealingAmountTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_uses_harga_dealing_when_booking_still_confirm(): void
    {
        $ctx = $this->context();
        $booking = $this->booking($ctx, [
            'status' => 'confirm',
            'harga_dealing' => 500000,
        ]);

        $invoice = app(ReceivableService::class)->generateInvoice([$booking->id], $ctx['branch']->id, $ctx['tenant']->id);

        $this->assertSame(500000, (int) $invoice->total_amount);
        $this->assertCount(1, $invoice->items_snapshot);
        $this->assertSame(500000, (int) $invoice->items_snapshot[0]['amount']);
    }

    public function test_invoice_uses_item_based_amount_when_unit_already_handled(): void
    {
        $ctx = $this->context();
        // harga_dealing kosong dibanding harga_mobil item: pastikan harga item tetap dipakai begitu sudah waiting_list.
        $booking = $this->booking($ctx, [
            'status' => 'waiting_list',
            'harga_dealing' => 999999,
        ]);

        $invoice = app(ReceivableService::class)->generateInvoice([$booking->id], $ctx['branch']->id, $ctx['tenant']->id);

        $this->assertSame(300000, (int) $invoice->total_amount);
    }

    public function test_invoice_auto_refreshes_to_item_based_amount_when_booking_is_handled(): void
    {
        $ctx = $this->context();
        $booking = $this->booking($ctx, [
            'status' => 'confirm',
            'harga_dealing' => 500000,
        ]);
        $unit = $booking->bookingDetails->first()->unit;

        $invoice = app(ReceivableService::class)->generateInvoice([$booking->id], $ctx['branch']->id, $ctx['tenant']->id);
        $this->assertSame(500000, (int) $invoice->total_amount);

        app(BookingService::class)->handleBooking($booking->fresh(), [
            'unit_id' => $unit->id,
            'lama_sewa' => 1,
            'paket_sewa' => 'harian',
            'harga_mobil' => 700000,
            'pricing_mode' => 'non_all_in',
            'costs' => [],
        ]);

        $invoice = $invoice->fresh();
        $this->assertSame('waiting_list', $booking->fresh()->status);
        $this->assertSame(700000, (int) $invoice->total_amount);
    }

    private function context(): array
    {
        $tenant = Tenant::create(['name' => 'DRENT', 'slug' => uniqid('drent-'), 'is_active' => true]);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Admin',
            'email' => uniqid('admin-').'@example.test',
            'password' => 'password',
            'role' => 'admin_branch',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        return compact('tenant', 'branch', 'user');
    }

    private function booking(array $ctx, array $overrides = []): Booking
    {
        $customer = Customer::create([
            'tenant_id' => $ctx['tenant']->id,
            'nama' => 'Budi',
            'kontak_1' => uniqid('08'),
            'kota' => 'Jakarta',
            'status' => 'Normal',
        ]);

        $owner = RentalOwner::create([
            'tenant_id' => $ctx['tenant']->id,
            'nama' => 'Internal',
            'kontak_1' => uniqid('08'),
            'is_owner' => true,
        ]);

        $unit = Unit::create([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'rental_owner_id' => $owner->id,
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
