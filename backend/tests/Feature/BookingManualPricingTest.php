<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\RentalOwner;
use App\Models\RentToRentDebt;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\BookingBillingService;
use App\Services\BookingService;
use App\Services\RentToRentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingManualPricingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Branch $branch;
    private Unit $unit;
    private Customer $customer;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'DRENT', 'slug' => uniqid('drent-'), 'is_active' => true]);
        $this->branch = Branch::create(['tenant_id' => $this->tenant->id, 'name' => 'Main']);

        $owner = RentalOwner::create([
            'tenant_id' => $this->tenant->id,
            'nama' => 'Rental Mitra',
            'kontak_1' => '08123456789',
            'bank' => 'BCA',
            'no_rek' => '123456',
            'atas_nama' => 'Rental Mitra',
            'is_owner' => false,
        ]);

        $this->unit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
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
            'harga_all_in' => 450000,
            'modal_all_in' => 150000,
            'status' => 'Aktif',
        ]);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'nama' => 'Customer A',
            'kontak_1' => '089999999',
            'kota' => 'Jakarta',
            'status' => 'Normal',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Admin',
            'email' => uniqid('admin-').'@example.test',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($this->user);
    }

    private function createBooking(string $status = 'follow_up', int $lamaSewa = 5): Booking
    {
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'kode_booking' => uniqid('BK-'),
            'status' => $status,
            'lama_sewa' => $lamaSewa,
            'paket_sewa' => 'harian',
        ]);

        $booking->bookingDetails()->create([
            'unit_id' => null,
            'tgl_sewa' => '2026-05-16 07:00:00',
            'tgl_kembali' => '2026-05-20 23:59:00',
            'lama_sewa' => $lamaSewa,
            'paket_sewa' => 'harian',
            'detail_type' => 'initial',
            'status' => 'draft',
        ]);

        return $booking;
    }

    private function handlePayload(array $overrides = []): array
    {
        return array_merge([
            'unit_id' => $this->unit->id,
            'lama_sewa' => 5,
            'paket_sewa' => 'harian',
            'harga_mobil' => 350000,
            'diskon_mobil' => 200000,
            'pricing_mode' => 'non_all_in',
            'costs' => [],
        ], $overrides);
    }

    /** @test */
    public function test_diskon_mobil_is_total_discount_not_per_period(): void
    {
        $booking = $this->createBooking();
        app(BookingService::class)->handleBooking($booking, $this->handlePayload());

        $detail = $booking->fresh()->bookingDetails()->first();
        $billing = app(BookingBillingService::class);

        // 350.000 x 5 hari - 200.000 diskon total = 1.550.000
        $this->assertSame(1550000, $billing->detailRentalSubtotal($detail));
        $this->assertSame(1550000, $billing->totalTagihan($booking->fresh()));
    }

    /** @test */
    public function test_rental_subtotal_is_clamped_to_zero_when_discount_exceeds_total(): void
    {
        $detail = new BookingDetail([
            'harga_mobil' => 100000,
            'diskon_mobil' => 900000,
            'lama_sewa' => 3,
        ]);

        $this->assertSame(0, app(BookingBillingService::class)->detailRentalSubtotal($detail));
    }

    /** @test */
    public function test_manual_modal_mobil_is_snapshotted_without_touching_master_unit(): void
    {
        $booking = $this->createBooking();
        app(BookingService::class)->handleBooking($booking, $this->handlePayload([
            'modal_mobil' => 175000,
        ]));

        $detail = $booking->fresh()->bookingDetails()->first();

        $this->assertSame(175000, (int) $detail->modal_mobil);
        $this->assertSame(350000, (int) $detail->harga_mobil);

        // Master unit tidak boleh ikut berubah saat booking di-handle.
        $this->assertSame(100000, (int) $this->unit->fresh()->modal_1_hari);
        $this->assertSame(300000, (int) $this->unit->fresh()->harga_1_hari);
    }

    /** @test */
    public function test_modal_mobil_falls_back_to_master_unit_when_not_provided(): void
    {
        $booking = $this->createBooking(lamaSewa: 2);
        app(BookingService::class)->handleBooking($booking, $this->handlePayload([
            'lama_sewa' => 2,
            'paket_sewa' => 'mingguan',
        ]));

        $detail = $booking->fresh()->bookingDetails()->first();

        $this->assertSame(700000, (int) $detail->modal_mobil);
    }

    /** @test */
    public function test_rent_to_rent_amount_uses_manual_modal_snapshot(): void
    {
        $booking = $this->createBooking();
        app(BookingService::class)->handleBooking($booking, $this->handlePayload([
            'modal_mobil' => 175000,
        ]));

        $detail = $booking->fresh()->bookingDetails()->first();
        $debt = RentToRentDebt::where('booking_detail_id', $detail->id)->firstOrFail();

        // Modal manual per hari x 5 hari.
        $this->assertSame(175000 * 5, app(RentToRentService::class)->currentAmount($debt));
    }

    /** @test */
    public function test_assign_detail_accepts_manual_price_and_modal(): void
    {
        $booking = $this->createBooking(status: 'waiting_list');

        $detail = app(BookingService::class)->assignDetail($booking, [
            'unit_id' => $this->unit->id,
            'tgl_sewa' => '2026-05-16 07:00:00',
            'tgl_kembali' => '2026-05-20 23:59:00',
            'harga_mobil' => 320000,
            'modal_mobil' => 120000,
            'diskon_mobil' => 100000,
            'lama_sewa' => 5,
            'paket_sewa' => 'harian',
            'pricing_mode' => 'non_all_in',
        ]);

        $this->assertSame(120000, (int) $detail->modal_mobil);
        $this->assertSame(1500000, app(BookingBillingService::class)->detailRentalSubtotal($detail));
    }
}
