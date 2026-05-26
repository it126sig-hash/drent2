<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingPayment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentAccount;
use App\Models\RentalOwner;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_auth_and_returns_empty_state(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();

        $ctx = $this->context('admin_branch');

        $this->getJson('/api/v1/dashboard?date_from=2026-05-01&date_to=2026-05-31')
            ->assertOk()
            ->assertJsonPath('data.period.date_from', '2026-05-01')
            ->assertJsonPath('data.kpis.0.value', 0)
            ->assertJsonPath('data.repeat_order_leaderboards.0.status', 'Normal')
            ->assertJsonCount(4, 'data.repeat_order_leaderboards');

        $otherBranch = Branch::create(['tenant_id' => $ctx['tenant']->id, 'name' => 'Other']);

        $this->getJson("/api/v1/dashboard?branch_id={$otherBranch->id}")
            ->assertForbidden();
    }

    public function test_dashboard_scopes_branch_and_uses_only_completed_bookings_for_repeat_leaderboards(): void
    {
        $ctx = $this->context('admin_branch');
        $otherBranch = Branch::create(['tenant_id' => $ctx['tenant']->id, 'name' => 'Other']);
        $account = $this->paymentAccount($ctx);
        $normal = $this->customer($ctx, 'Normal', 'Budi Normal');
        $member = $this->customer($ctx, 'Member', 'Maya Member');
        $corporate = $this->customer($ctx, 'Corporate', 'PT Maju');
        $externalOwner = $this->owner($ctx['tenant']->id, false, 'Rental Mitra');
        $internalOwner = $this->owner($ctx['tenant']->id, true, 'Internal');
        $externalUnit = $this->unit($ctx, $externalOwner->id);
        $internalUnit = $this->unit($ctx, $internalOwner->id);

        $this->completedBooking($ctx, $normal, $internalUnit, 500000, '2026-05-03 10:00:00');
        $this->completedBooking($ctx, $normal, $internalUnit, 700000, '2026-05-10 10:00:00');
        $this->completedBooking($ctx, $member, $internalUnit, 300000, '2026-05-11 10:00:00');
        $this->completedBooking($ctx, $corporate, $internalUnit, 900000, '2026-05-12 10:00:00');
        $this->completedBooking($ctx, $normal, $externalUnit, 450000, '2026-05-13 10:00:00');

        $this->booking($ctx, $normal, $internalUnit, ['status' => 'batal', 'harga_dealing' => 800000]);
        $this->booking($ctx, $normal, $internalUnit, ['status' => 'rental_unit', 'harga_dealing' => 800000]);
        $this->booking($ctx, $normal, $internalUnit, ['branch_id' => $otherBranch->id, 'status' => 'selesai', 'harga_dealing' => 999999, 'completed_at' => '2026-05-14 10:00:00']);

        BookingPayment::create([
            'booking_id' => Booking::where('customer_id', $normal->id)->where('status', 'selesai')->first()->id,
            'payment_account_id' => $account->id,
            'amount' => 250000,
            'payment_type' => 'dp',
            'paid_at' => '2026-05-04 10:00:00',
            'created_by' => $ctx['user']->id,
        ]);

        $response = $this->getJson('/api/v1/dashboard?date_from=2026-05-01&date_to=2026-05-31')
            ->assertOk();

        $response->assertJsonPath('data.kpis.0.value', 1)
            ->assertJsonPath('data.kpis.0.display_value', '1 / 1')
            ->assertJsonPath('data.kpis.1.value', 1)
            ->assertJsonPath('data.kpis.2.value', 5)
            ->assertJsonPath('data.kpis.3.value', 250000);

        $armadaStatus = collect($response->json('data.armada_status'));
        $this->assertEquals(1, $armadaStatus->firstWhere('key', 'available')['value']);
        $this->assertEquals(0, $armadaStatus->firstWhere('key', 'rented')['value']);

        $normalBoard = collect($response->json('data.repeat_order_leaderboards'))->firstWhere('status', 'Normal');
        $memberBoard = collect($response->json('data.repeat_order_leaderboards'))->firstWhere('status', 'Member');
        $corporateBoard = collect($response->json('data.repeat_order_leaderboards'))->firstWhere('status', 'Corporate');
        $rentToRentBoard = collect($response->json('data.repeat_order_leaderboards'))->firstWhere('status', 'Rent to Rent');

        $this->assertSame('Budi Normal', $normalBoard['items'][0]['name']);
        $this->assertSame(3, $normalBoard['items'][0]['total_bookings']);
        $this->assertArrayNotHasKey('total_amount', $normalBoard['items'][0]);
        $this->assertSame('Maya Member', $memberBoard['items'][0]['name']);
        $this->assertSame('PT Maju', $corporateBoard['items'][0]['name']);
        $this->assertSame('Rental Mitra', $rentToRentBoard['items'][0]['name']);
        $this->assertSame('rental_owner', $rentToRentBoard['items'][0]['source']);
    }

    public function test_superadmin_can_filter_dashboard_by_branch(): void
    {
        $ctx = $this->context('superadmin');
        $branchA = $ctx['branch'];
        $branchB = Branch::create(['tenant_id' => $ctx['tenant']->id, 'name' => 'Branch B']);
        $customer = $this->customer($ctx, 'Normal', 'Scoped Customer');
        $owner = $this->owner($ctx['tenant']->id, true, 'Internal');
        $unitA = $this->unit($ctx, $owner->id);
        $unitB = $this->unit([...$ctx, 'branch' => $branchB], $owner->id);

        $this->completedBooking([...$ctx, 'branch' => $branchA], $customer, $unitA, 100000, '2026-05-02 10:00:00');
        $this->completedBooking([...$ctx, 'branch' => $branchB], $customer, $unitB, 200000, '2026-05-03 10:00:00');

        $this->getJson("/api/v1/dashboard?branch_id={$branchB->id}&date_from=2026-05-01&date_to=2026-05-31")
            ->assertOk()
            ->assertJsonPath('data.kpis.2.value', 1)
            ->assertJsonPath('data.repeat_order_leaderboards.0.items.0.total_bookings', 1);
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

    private function completedBooking(array $ctx, Customer $customer, Unit $unit, int $amount, string $completedAt): Booking
    {
        return $this->booking($ctx, $customer, $unit, [
            'status' => 'selesai',
            'harga_dealing' => $amount,
            'completed_at' => $completedAt,
            'updated_at' => $completedAt,
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
            'status' => 'selesai',
        ]);

        return $booking;
    }
}
