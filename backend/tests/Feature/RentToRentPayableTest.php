<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentAccount;
use App\Models\RentalOwner;
use App\Models\RentToRentBill;
use App\Models\RentToRentDebt;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RentToRentPayableTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_handled_external_owner_bookings_and_uses_live_modal(): void
    {
        $ctx = $this->context();
        $externalOwner = $this->owner($ctx['tenant']->id, false, 'Rental Mitra');
        $internalOwner = $this->owner($ctx['tenant']->id, true, 'Internal');

        $externalUnit = $this->unit($ctx, $externalOwner->id, ['modal_1_hari' => 150000]);
        $internalUnit = $this->unit($ctx, $internalOwner->id, ['modal_1_hari' => 90000]);

        $this->bookingWithDetail($ctx, $externalUnit->id, ['status' => 'waiting_list'], ['lama_sewa' => 2]);
        $this->bookingWithDetail($ctx, $internalUnit->id, ['status' => 'waiting_list'], ['lama_sewa' => 5]);
        $this->bookingWithDetail($ctx, $externalUnit->id, ['status' => 'confirm'], ['lama_sewa' => 3]);
        $this->bookingWithDetail($ctx, $externalUnit->id, ['status' => 'batal'], ['lama_sewa' => 3]);

        $response = $this->getJson('/api/v1/rent-to-rent');

        $response->assertOk()
            ->assertJsonPath('summary.debt_count', 1)
            ->assertJsonPath('summary.total_amount', 300000)
            ->assertJsonPath('data.0.rental_owner.nama', 'Rental Mitra')
            ->assertJsonPath('data.0.total_amount', 300000);
    }

    public function test_it_rejects_bill_generation_for_mixed_owners(): void
    {
        $ctx = $this->context();
        $ownerA = $this->owner($ctx['tenant']->id, false, 'Rental A');
        $ownerB = $this->owner($ctx['tenant']->id, false, 'Rental B');

        $this->bookingWithDetail($ctx, $this->unit($ctx, $ownerA->id)->id);
        $this->bookingWithDetail($ctx, $this->unit($ctx, $ownerB->id)->id);
        $this->getJson('/api/v1/rent-to-rent')->assertOk();

        $response = $this->postJson('/api/v1/rent-to-rent/bills', [
            'debt_ids' => RentToRentDebt::pluck('id')->all(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('debt_ids');
    }

    public function test_it_creates_bill_locks_override_and_allocates_partial_payment(): void
    {
        $ctx = $this->context();
        $owner = $this->owner($ctx['tenant']->id, false, 'Rental Mitra');
        $unit = $this->unit($ctx, $owner->id, ['modal_1_hari' => 100000]);
        $account = PaymentAccount::create([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'nama_bank' => 'BCA',
            'nomor_rekening' => '123',
            'atas_nama' => 'DRENT',
            'is_active' => true,
        ]);

        $first = $this->bookingWithDetail($ctx, $unit->id, detailOverrides: ['lama_sewa' => 2]);
        $second = $this->bookingWithDetail($ctx, $unit->id, detailOverrides: ['lama_sewa' => 3]);
        $this->getJson('/api/v1/rent-to-rent')->assertOk();

        $secondDebt = RentToRentDebt::where('booking_detail_id', $second['detail']->id)->firstOrFail();
        $this->patchJson("/api/v1/rent-to-rent/{$secondDebt->id}/amount", [
            'amount_override' => 450000,
        ])->assertOk()->assertJsonPath('data.total_amount', 450000);

        $debtIds = RentToRentDebt::orderBy('id')->pluck('id')->all();
        $billId = $this->postJson('/api/v1/rent-to-rent/bills', [
            'debt_ids' => $debtIds,
        ])->assertOk()
            ->assertJsonPath('data.total_amount', 650000)
            ->json('data.id');

        $this->patchJson("/api/v1/rent-to-rent/{$secondDebt->id}/amount", [
            'amount_override' => 500000,
        ])->assertStatus(422);

        $this->postJson("/api/v1/rent-to-rent/bills/{$billId}/payments", [
            'payment_account_id' => $account->id,
            'amount' => 250000,
            'paid_at' => '2026-05-16',
        ])->assertOk()
            ->assertJsonPath('data.paid_amount', 250000)
            ->assertJsonPath('data.status', 'partial_paid');

        $list = collect($this->getJson('/api/v1/rent-to-rent')->assertOk()->json('data'));
        $firstRow = $list->firstWhere('booking_detail_id', $first['detail']->id);
        $secondRow = $list->firstWhere('booking_detail_id', $second['detail']->id);

        $this->assertSame('paid', $firstRow['status']);
        $this->assertSame('partial_paid', $secondRow['status']);
        $this->assertSame(50000, $secondRow['paid_amount']);
    }

    public function test_public_bill_pdf_and_void_approval_voids_all_payments(): void
    {
        $ctx = $this->context();
        $owner = $this->owner($ctx['tenant']->id, false, 'Rental Mitra');
        $unit = $this->unit($ctx, $owner->id, ['modal_1_hari' => 100000]);
        $account = PaymentAccount::create([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'nama_bank' => 'BCA',
            'nomor_rekening' => '123',
            'atas_nama' => 'DRENT',
            'is_active' => true,
        ]);

        $this->bookingWithDetail($ctx, $unit->id, detailOverrides: ['lama_sewa' => 2]);
        $this->getJson('/api/v1/rent-to-rent')->assertOk();

        $billId = $this->postJson('/api/v1/rent-to-rent/bills', [
            'debt_ids' => RentToRentDebt::pluck('id')->all(),
        ])->assertOk()->json('data.id');

        $this->postJson("/api/v1/rent-to-rent/bills/{$billId}/payments", [
            'payment_account_id' => $account->id,
            'amount' => 50000,
            'paid_at' => '2026-05-16',
        ])->assertOk();

        $bill = RentToRentBill::findOrFail($billId);
        $this->assertNotNull($bill->public_token);
        $this->getJson("/api/v1/public/rent-to-rent-bills/{$bill->public_token}")
            ->assertOk()
            ->assertJsonPath('data.bill_number', $bill->bill_number);
        $this->get("/api/v1/public/rent-to-rent-bills/{$bill->public_token}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->postJson("/api/v1/rent-to-rent/bills/{$billId}/request-void", [
            'void_reason' => 'Nominal pembayaran salah input',
        ])->assertOk()
            ->assertJsonPath('data.status', 'void_requested');

        $supervisor = User::create([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'name' => 'Supervisor',
            'email' => uniqid('supervisor-').'@example.test',
            'password' => 'password',
            'role' => 'supervisor',
            'is_active' => true,
        ]);

        Sanctum::actingAs($supervisor);

        $this->postJson("/api/v1/rent-to-rent/bills/{$billId}/approve-void")
            ->assertOk()
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.paid_amount', 0);

        $bill->refresh();
        $this->assertSame('voided', $bill->payments()->first()->status);
        $this->assertSame('open', RentToRentDebt::first()->status);
    }

    private function context(): array
    {
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

        Sanctum::actingAs($user);

        return compact('tenant', 'branch', 'user');
    }

    private function owner(int $tenantId, bool $isOwner, string $name): RentalOwner
    {
        return RentalOwner::create([
            'tenant_id' => $tenantId,
            'nama' => $name,
            'kontak_1' => '08123456789',
            'bank' => 'BCA',
            'no_rek' => '123456',
            'atas_nama' => $name,
            'is_owner' => $isOwner,
        ]);
    }

    private function unit(array $ctx, int $ownerId, array $overrides = []): Unit
    {
        return Unit::create(array_merge([
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
        ], $overrides));
    }

    private function bookingWithDetail(array $ctx, int $unitId, array $bookingOverrides = [], array $detailOverrides = []): array
    {
        $customer = Customer::create([
            'tenant_id' => $ctx['tenant']->id,
            'nama' => uniqid('Customer '),
            'kontak_1' => '089999999',
            'kota' => 'Jakarta',
            'status' => 'Normal',
        ]);

        $booking = Booking::create(array_merge([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'customer_id' => $customer->id,
            'created_by' => $ctx['user']->id,
            'kode_booking' => uniqid('BK-'),
            'status' => 'waiting_list',
            'lama_sewa' => 1,
            'paket_sewa' => 'harian',
            'tujuan' => 'Bandung',
        ], $bookingOverrides));

        $detail = BookingDetail::create(array_merge([
            'booking_id' => $booking->id,
            'unit_id' => $unitId,
            'tgl_sewa' => '2026-05-16 07:00:00',
            'tgl_kembali' => '2026-05-17 23:59:00',
            'harga_mobil' => 300000,
            'diskon_mobil' => 0,
            'lama_sewa' => 1,
            'paket_sewa' => 'harian',
            'pricing_mode' => 'non_all_in',
            'detail_type' => 'initial',
            'status' => 'draft',
        ], $detailOverrides));

        return compact('booking', 'detail');
    }
}
