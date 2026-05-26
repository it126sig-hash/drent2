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
use App\Services\RentToRentService;
use App\Http\Resources\RentToRentDebtResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class RentToRentPricingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Branch $branch;
    private RentalOwner $owner;
    private Unit $unit;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'DRENT', 'slug' => uniqid('drent-'), 'is_active' => true]);
        $this->branch = Branch::create(['tenant_id' => $this->tenant->id, 'name' => 'Main']);
        
        $this->owner = RentalOwner::create([
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
            'rental_owner_id' => $this->owner->id,
            'tipe' => 'Avanza',
            'merk' => 'Toyota',
            'tahun' => 2024,
            'no_polisi' => uniqid('B'),
            // Non-all-in selling prices
            'harga_1_hari' => 300000,
            'harga_1_minggu' => 1800000,
            'harga_1_bulan' => 6000000,
            // Non-all-in modal (cost) prices
            'modal_1_hari' => 100000,
            'modal_1_minggu' => 700000,
            'modal_1_bulan' => 2500000,
            // All-in selling prices
            'harga_all_in' => 450000,
            'harga_all_in_1_minggu' => 2700000,
            'harga_all_in_1_bulan' => 9000000,
            // All-in modal (cost) prices
            'modal_all_in' => 150000,
            'modal_all_in_1_minggu' => 1050000,
            'modal_all_in_1_bulan' => 3800000,
            'status' => 'Aktif',
        ]);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'nama' => 'Customer A',
            'kontak_1' => '089999999',
            'kota' => 'Jakarta',
            'status' => 'Normal',
        ]);
    }

    private function createDebt(string $pricingMode, $usePricingPackage, string $paketSewa, int $lamaSewa): RentToRentDebt
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Finance',
            'email' => uniqid('finance-').'@example.test',
            'password' => 'password',
            'role' => 'finance',
            'is_active' => true,
        ]);

        $packageId = null;
        if ($usePricingPackage) {
            $package = \App\Models\PricingPackage::create([
                'tenant_id' => $this->tenant->id,
                'branch_id' => $this->branch->id,
                'nama_paket' => 'Paket Test',
                'harga' => 300000,
                'is_active' => true,
            ]);
            $packageId = $package->id;
        }

        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'created_by' => $user->id,
            'kode_booking' => uniqid('BK-'),
            'status' => 'waiting_list',
            'lama_sewa' => $lamaSewa,
            'paket_sewa' => $paketSewa,
        ]);

        $detail = BookingDetail::create([
            'booking_id' => $booking->id,
            'unit_id' => $this->unit->id,
            'tgl_sewa' => '2026-05-16 07:00:00',
            'tgl_kembali' => '2026-05-18 23:59:00',
            'harga_mobil' => 300000,
            'lama_sewa' => $lamaSewa,
            'paket_sewa' => $paketSewa,
            'pricing_mode' => $pricingMode,
            'pricing_package_id' => $packageId,
            'status' => 'draft',
        ]);

        app(RentToRentService::class)->syncDetail($detail->fresh(['booking', 'unit.rentalOwner']));

        return RentToRentDebt::where('booking_detail_id', $detail->id)->firstOrFail();
    }

    /** @test */
    public function test_pricing_mode_non_all_in(): void
    {
        $service = app(RentToRentService::class);

        // Daily
        $debtDaily = $this->createDebt('non_all_in', false, 'harian', 3);
        $this->assertSame(100000 * 3, $service->currentAmount($debtDaily));
        $this->assertNull($service->sellingPrice($debtDaily));

        // Weekly
        $debtWeekly = $this->createDebt('non_all_in', false, 'mingguan', 2);
        $this->assertSame(700000 * 2, $service->currentAmount($debtWeekly));
        $this->assertNull($service->sellingPrice($debtWeekly));

        // Monthly
        $debtMonthly = $this->createDebt('non_all_in', false, 'bulanan', 1);
        $this->assertSame(2500000 * 1, $service->currentAmount($debtMonthly));
        $this->assertNull($service->sellingPrice($debtMonthly));
    }

    /** @test */
    public function test_pricing_mode_all_in_with_pricing_package(): void
    {
        $service = app(RentToRentService::class);

        // Daily: all_in & has pricing package -> use modal_1_* & harga_1_*
        $debtDaily = $this->createDebt('all_in', true, 'harian', 3);
        $this->assertSame(100000 * 3, $service->currentAmount($debtDaily));
        $this->assertSame(300000 * 3, $service->sellingPrice($debtDaily));

        // Weekly: all_in & has pricing package -> use modal_1_* & harga_1_*
        $debtWeekly = $this->createDebt('all_in', true, 'mingguan', 2);
        $this->assertSame(700000 * 2, $service->currentAmount($debtWeekly));
        $this->assertSame(1800000 * 2, $service->sellingPrice($debtWeekly));

        // Monthly: all_in & has pricing package -> use modal_1_* & harga_1_*
        $debtMonthly = $this->createDebt('all_in', true, 'bulanan', 1);
        $this->assertSame(2500000 * 1, $service->currentAmount($debtMonthly));
        $this->assertSame(6000000 * 1, $service->sellingPrice($debtMonthly));
    }

    /** @test */
    public function test_pricing_mode_all_in_without_pricing_package(): void
    {
        $service = app(RentToRentService::class);

        // Daily: all_in & tanpa pricing package -> use modal_all_in & harga_all_in
        $debtDaily = $this->createDebt('all_in', null, 'harian', 3);
        $this->assertSame(150000 * 3, $service->currentAmount($debtDaily));
        $this->assertSame(450000 * 3, $service->sellingPrice($debtDaily));

        // Weekly: all_in & tanpa pricing package -> use modal_all_in_1_minggu & harga_all_in_1_minggu
        $debtWeekly = $this->createDebt('all_in', null, 'mingguan', 2);
        $this->assertSame(1050000 * 2, $service->currentAmount($debtWeekly));
        $this->assertSame(2700000 * 2, $service->sellingPrice($debtWeekly));

        // Monthly: all_in & tanpa pricing package -> use modal_all_in_1_bulan & harga_all_in_1_bulan
        $debtMonthly = $this->createDebt('all_in', null, 'bulanan', 1);
        $this->assertSame(3800000 * 1, $service->currentAmount($debtMonthly));
        $this->assertSame(9000000 * 1, $service->sellingPrice($debtMonthly));
    }

    /** @test */
    public function test_resource_exposes_pricing_fields(): void
    {
        $debt = $this->createDebt('all_in', null, 'harian', 3);
        
        $resource = new RentToRentDebtResource($debt->load(['bookingDetail.unit', 'rentalOwner', 'booking', 'billItems']));
        $data = $resource->toArray(new Request());

        $this->assertSame('all_in', $data['pricing_mode']);
        $this->assertSame(450000 * 3, $data['selling_price']);
        
        $unitData = $data['unit'];
        $this->assertSame(150000, $unitData['modal_all_in']);
        $this->assertSame(450000, $unitData['harga_all_in']);
        $this->assertSame(1050000, $unitData['modal_all_in_1_minggu']);
        $this->assertSame(2700000, $unitData['harga_all_in_1_minggu']);
    }
}
