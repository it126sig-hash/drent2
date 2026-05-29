<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\RentalOwner;
use App\Models\RentToRentDebt;
use App\Models\RentToRentAmountChangeRequest;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\RentToRentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RentToRentAmountChangeTest extends TestCase
{
    use RefreshDatabase;

    private array $ctx;
    private RentToRentDebt $debt;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['name' => 'DRENT', 'slug' => uniqid('drent-'), 'is_active' => true]);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Finance',
            'email' => uniqid('finance-').'@example.test',
            'password' => 'password',
            'role' => 'finance',
            'is_active' => true,
        ]);

        \App\Models\RolePermission::create([
            'tenant_id' => $tenant->id,
            'role' => 'finance',
            'permission_key' => 'finance.rent_to_rent',
        ]);

        \App\Models\RolePermission::create([
            'tenant_id' => $tenant->id,
            'role' => 'supervisor',
            'permission_key' => 'finance.rent_to_rent',
        ]);

        Sanctum::actingAs($user);

        $this->ctx = compact('tenant', 'branch', 'user');

        $owner = RentalOwner::create([
            'tenant_id' => $tenant->id,
            'nama' => 'Rental Mitra',
            'kontak_1' => '08123456789',
            'bank' => 'BCA',
            'no_rek' => '123456',
            'atas_nama' => 'Rental Mitra',
            'is_owner' => false,
        ]);

        $unit = Unit::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
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

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'nama' => 'Customer A',
            'kontak_1' => '089999999',
            'kota' => 'Jakarta',
            'status' => 'Normal',
        ]);

        $booking = Booking::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'kode_booking' => uniqid('BK-'),
            'status' => 'waiting_list',
            'lama_sewa' => 2,
            'paket_sewa' => 'harian',
        ]);

        $detail = BookingDetail::create([
            'booking_id' => $booking->id,
            'unit_id' => $unit->id,
            'tgl_sewa' => '2026-05-16 07:00:00',
            'tgl_kembali' => '2026-05-18 23:59:00',
            'harga_mobil' => 300000,
            'lama_sewa' => 2,
            'paket_sewa' => 'harian',
            'status' => 'draft',
        ]);

        app(RentToRentService::class)->syncDetail($detail->fresh(['booking', 'unit.rentalOwner']));

        $this->debt = RentToRentDebt::firstOrFail();
    }

    public function test_finance_can_request_amount_change_with_reason(): void
    {
        $response = $this->postJson("/api/v1/rent-to-rent/debts/{$this->debt->id}/amount-change-requests", [
            'amount_override' => 250000,
            'reason' => 'Negosiasi harga modal rental',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.requested_amount_override', 250000)
            ->assertJsonPath('data.reason', 'Negosiasi harga modal rental')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('rent_to_rent_amount_change_requests', [
            'rent_to_rent_debt_id' => $this->debt->id,
            'requested_amount_override' => 250000,
            'reason' => 'Negosiasi harga modal rental',
            'status' => 'pending',
        ]);

        // Debt amount_override should not change yet
        $this->debt->refresh();
        $this->assertNull($this->debt->amount_override);
    }

    public function test_cannot_request_without_reason_or_short_reason(): void
    {
        $response = $this->postJson("/api/v1/rent-to-rent/debts/{$this->debt->id}/amount-change-requests", [
            'amount_override' => 250000,
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['reason']);

        $response = $this->postJson("/api/v1/rent-to-rent/debts/{$this->debt->id}/amount-change-requests", [
            'amount_override' => 250000,
            'reason' => 'abc',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['reason']);
    }

    public function test_finance_cannot_request_amount_change_if_already_pending(): void
    {
        // First request
        $this->postJson("/api/v1/rent-to-rent/debts/{$this->debt->id}/amount-change-requests", [
            'amount_override' => 250000,
            'reason' => 'Negosiasi harga pertama',
        ])->assertCreated();

        // Second request should fail
        $response = $this->postJson("/api/v1/rent-to-rent/debts/{$this->debt->id}/amount-change-requests", [
            'amount_override' => 240000,
            'reason' => 'Negosiasi harga kedua',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Sudah ada permintaan perubahan nominal yang sedang menunggu persetujuan.');
    }

    public function test_user_can_cancel_own_pending_request(): void
    {
        $req = RentToRentAmountChangeRequest::create([
            'rent_to_rent_debt_id' => $this->debt->id,
            'requested_amount_override' => 220000,
            'reason' => 'Alasan negosiasi',
            'status' => 'pending',
            'requested_by' => $this->ctx['user']->id,
            'requested_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/rent-to-rent/amount-change-requests/{$req->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('rent_to_rent_amount_change_requests', [
            'id' => $req->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_other_user_cannot_cancel_pending_request(): void
    {
        $otherUser = User::create([
            'tenant_id' => $this->ctx['tenant']->id,
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Finance 2',
            'email' => uniqid('finance-').'@example.test',
            'password' => 'password',
            'role' => 'finance',
            'is_active' => true,
        ]);

        $req = RentToRentAmountChangeRequest::create([
            'rent_to_rent_debt_id' => $this->debt->id,
            'requested_amount_override' => 220000,
            'reason' => 'Alasan negosiasi',
            'status' => 'pending',
            'requested_by' => $otherUser->id,
            'requested_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/rent-to-rent/amount-change-requests/{$req->id}/cancel");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Anda hanya dapat membatalkan permintaan yang Anda buat sendiri.');
    }

    public function test_supervisor_can_approve_amount_change(): void
    {
        $req = RentToRentAmountChangeRequest::create([
            'rent_to_rent_debt_id' => $this->debt->id,
            'requested_amount_override' => 220000,
            'reason' => 'Alasan negosiasi',
            'status' => 'pending',
            'requested_by' => $this->ctx['user']->id,
            'requested_at' => now(),
        ]);

        $supervisor = User::create([
            'tenant_id' => $this->ctx['tenant']->id,
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Supervisor',
            'email' => uniqid('supervisor-').'@example.test',
            'password' => 'password',
            'role' => 'supervisor',
            'is_active' => true,
        ]);

        Sanctum::actingAs($supervisor);

        $response = $this->postJson("/api/v1/rent-to-rent/amount-change-requests/{$req->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('rent_to_rent_amount_change_requests', [
            'id' => $req->id,
            'status' => 'approved',
            'approved_by' => $supervisor->id,
        ]);

        // Debt amount should be updated
        $this->debt->refresh();
        $this->assertSame(220000, $this->debt->amount_override);
        $this->assertSame(220000, $this->debt->cached_total_amount); // amount_override is total amount
    }

    public function test_supervisor_can_reject_amount_change_with_note(): void
    {
        $req = RentToRentAmountChangeRequest::create([
            'rent_to_rent_debt_id' => $this->debt->id,
            'requested_amount_override' => 220000,
            'reason' => 'Alasan negosiasi',
            'status' => 'pending',
            'requested_by' => $this->ctx['user']->id,
            'requested_at' => now(),
        ]);

        $supervisor = User::create([
            'tenant_id' => $this->ctx['tenant']->id,
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Supervisor',
            'email' => uniqid('supervisor-').'@example.test',
            'password' => 'password',
            'role' => 'supervisor',
            'is_active' => true,
        ]);

        Sanctum::actingAs($supervisor);

        $response = $this->postJson("/api/v1/rent-to-rent/amount-change-requests/{$req->id}/reject", [
            'rejection_note' => 'Terlalu murah',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('rent_to_rent_amount_change_requests', [
            'id' => $req->id,
            'status' => 'rejected',
            'rejected_by' => $supervisor->id,
            'rejection_note' => 'Terlalu murah',
        ]);

        // Debt amount should not change
        $this->debt->refresh();
        $this->assertNull($this->debt->amount_override);
    }

    public function test_non_supervisor_cannot_approve_or_reject(): void
    {
        $req = RentToRentAmountChangeRequest::create([
            'rent_to_rent_debt_id' => $this->debt->id,
            'requested_amount_override' => 220000,
            'reason' => 'Alasan negosiasi',
            'status' => 'pending',
            'requested_by' => $this->ctx['user']->id,
            'requested_at' => now(),
        ]);

        // Acting as Finance again (non-supervisor)
        Sanctum::actingAs($this->ctx['user']);

        $this->postJson("/api/v1/rent-to-rent/amount-change-requests/{$req->id}/approve")
            ->assertStatus(403);

        $this->postJson("/api/v1/rent-to-rent/amount-change-requests/{$req->id}/reject", [
            'rejection_note' => 'No',
        ])->assertStatus(403);
    }

    public function test_appears_in_supervisor_requests(): void
    {
        $req = RentToRentAmountChangeRequest::create([
            'rent_to_rent_debt_id' => $this->debt->id,
            'requested_amount_override' => 220000,
            'reason' => 'Alasan negosiasi',
            'status' => 'pending',
            'requested_by' => $this->ctx['user']->id,
            'requested_at' => now(),
        ]);

        $supervisor = User::create([
            'tenant_id' => $this->ctx['tenant']->id,
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Supervisor',
            'email' => uniqid('supervisor-').'@example.test',
            'password' => 'password',
            'role' => 'supervisor',
            'is_active' => true,
        ]);

        Sanctum::actingAs($supervisor);

        $response = $this->getJson('/api/v1/supervisor-requests');

        $response->assertOk()
            ->assertJsonPath('data.0.id', 'r2r_amount_change_'.$req->id)
            ->assertJsonPath('data.0.type', 'rent_to_rent_amount_change')
            ->assertJsonPath('data.0.type_label', 'Ubah Nominal Rent to Rent')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.reason', 'Alasan negosiasi')
            ->assertJsonPath('data.0.debt.requested_amount', 220000);
    }
}
