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
use App\Models\RentToRentPayment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\RentToRentService;
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

        Sanctum::actingAs($ctx['user']);

        $this->getJson('/api/v1/rent-to-rent')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'open');
    }

    public function test_it_records_direct_payment_without_generating_bill(): void
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

        $booking = $this->bookingWithDetail($ctx, $unit->id, detailOverrides: ['lama_sewa' => 2]);
        $debt = RentToRentDebt::where('booking_detail_id', $booking['detail']->id)->firstOrFail();

        $this->postJson("/api/v1/rent-to-rent/{$debt->id}/payments", [
            'payment_account_id' => $account->id,
            'amount' => 75000,
            'paid_at' => '2026-05-18',
        ])->assertOk()
            ->assertJsonPath('data.status', 'partial_paid')
            ->assertJsonPath('data.bill', null)
            ->assertJsonPath('data.paid_amount', 75000)
            ->assertJsonPath('data.remaining_amount', 125000);

        $this->assertSame(0, RentToRentBill::count());

        $payment = RentToRentPayment::firstOrFail();
        $this->assertNull($payment->rent_to_rent_bill_id);
        $this->assertNull($payment->allocations()->firstOrFail()->rent_to_rent_bill_item_id);

        $this->getJson('/api/v1/rent-to-rent/payment-history')
            ->assertOk()
            ->assertJsonPath('data.latest.0.bill_number', null)
            ->assertJsonPath('data.latest.0.owner_name', 'Rental Mitra')
            ->assertJsonPath('data.latest.0.amount', 75000);
    }

    public function test_direct_payments_can_be_paid_in_stages_and_voided_from_history(): void
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

        $booking = $this->bookingWithDetail($ctx, $unit->id, detailOverrides: ['lama_sewa' => 2]);
        $debt = RentToRentDebt::where('booking_detail_id', $booking['detail']->id)->firstOrFail();

        $this->postJson("/api/v1/rent-to-rent/{$debt->id}/payments", [
            'payment_account_id' => $account->id,
            'amount' => 75000,
            'paid_at' => '2026-05-18',
        ])->assertOk()
            ->assertJsonPath('data.status', 'partial_paid');

        $this->postJson("/api/v1/rent-to-rent/{$debt->id}/payments", [
            'payment_account_id' => $account->id,
            'amount' => 125000,
            'paid_at' => '2026-05-19',
        ])->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.remaining_amount', 0);

        $paymentId = RentToRentPayment::orderBy('paid_at')->firstOrFail()->id;

        $this->postJson("/api/v1/rent-to-rent/payments/{$paymentId}/void", [
            'void_reason' => 'Nominal salah input',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'void_requested')
            ->assertJsonPath('data.void_reason', 'Nominal salah input');

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

        $this->getJson('/api/v1/supervisor-requests')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'rent_to_rent_void_payment')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.reason', 'Nominal salah input');

        $this->postJson("/api/v1/rent-to-rent/payments/{$paymentId}/approve-void")
            ->assertOk()
            ->assertJsonPath('data.status', 'voided');

        Sanctum::actingAs($ctx['user']);

        $this->getJson('/api/v1/rent-to-rent/payment-history')
            ->assertOk()
            ->assertJsonPath('data.latest.1.status', 'voided');

        $this->getJson('/api/v1/rent-to-rent')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'partial_paid')
            ->assertJsonPath('data.0.paid_amount', 125000)
            ->assertJsonPath('data.0.remaining_amount', 75000);
    }

    public function test_debt_and_bill_can_be_tagged_paid_even_when_underpaid(): void
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

        $directBooking = $this->bookingWithDetail($ctx, $unit->id, detailOverrides: ['lama_sewa' => 2]);
        $directDebt = RentToRentDebt::where('booking_detail_id', $directBooking['detail']->id)->firstOrFail();

        $this->postJson("/api/v1/rent-to-rent/{$directDebt->id}/payments", [
            'payment_account_id' => $account->id,
            'amount' => 50000,
            'paid_at' => '2026-05-18',
        ])->assertOk();

        $this->postJson("/api/v1/rent-to-rent/{$directDebt->id}/mark-paid")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.paid_amount', 50000)
            ->assertJsonPath('data.remaining_amount', 150000);

        $billBooking = $this->bookingWithDetail($ctx, $unit->id, detailOverrides: ['lama_sewa' => 2]);
        $billDebt = RentToRentDebt::where('booking_detail_id', $billBooking['detail']->id)->firstOrFail();
        $billId = $this->postJson('/api/v1/rent-to-rent/bills', [
            'debt_ids' => [$billDebt->id],
        ])->assertOk()->json('data.id');

        $this->postJson("/api/v1/rent-to-rent/bills/{$billId}/payments", [
            'payment_account_id' => $account->id,
            'amount' => 50000,
            'paid_at' => '2026-05-18',
        ])->assertOk();

        $this->postJson("/api/v1/rent-to-rent/bills/{$billId}/mark-paid")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.paid_amount', 50000)
            ->assertJsonPath('data.remaining_amount', 150000);

        $this->getJson('/api/v1/rent-to-rent?status=paid')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_endpoint_does_not_create_missing_debts(): void
    {
        $ctx = $this->context();
        $owner = $this->owner($ctx['tenant']->id, false, 'Rental Mitra');

        $this->bookingWithDetail($ctx, $this->unit($ctx, $owner->id)->id, syncDebt: false);

        $this->assertSame(0, RentToRentDebt::count());

        $this->getJson('/api/v1/rent-to-rent')
            ->assertOk()
            ->assertJsonPath('summary.debt_count', 0)
            ->assertJsonCount(0, 'data');

        $this->assertSame(0, RentToRentDebt::count());
    }

    public function test_sync_cache_command_creates_missing_debts_and_populates_cache(): void
    {
        $ctx = $this->context();
        $owner = $this->owner($ctx['tenant']->id, false, 'Rental Mitra');
        $unit = $this->unit($ctx, $owner->id, ['modal_1_hari' => 125000]);
        $booking = $this->bookingWithDetail($ctx, $unit->id, detailOverrides: ['lama_sewa' => 2], syncDebt: false);

        $this->assertSame(0, RentToRentDebt::count());

        $this->artisan('rent-to-rent:sync-cache')->assertSuccessful();

        $debt = RentToRentDebt::where('booking_detail_id', $booking['detail']->id)->firstOrFail();
        $this->assertSame(250000, $debt->cached_total_amount);
        $this->assertSame(0, $debt->cached_paid_amount);
        $this->assertSame('open', $debt->cached_payment_status);
    }

    public function test_status_filter_uses_cached_payment_status(): void
    {
        $ctx = $this->context();
        $owner = $this->owner($ctx['tenant']->id, false, 'Rental Mitra');
        $unit = $this->unit($ctx, $owner->id);
        $openBooking = $this->bookingWithDetail($ctx, $unit->id);
        $paidBooking = $this->bookingWithDetail($ctx, $unit->id);

        RentToRentDebt::where('booking_detail_id', $paidBooking['detail']->id)->update([
            'cached_payment_status' => 'paid',
            'cached_paid_amount' => 100000,
        ]);

        $this->getJson('/api/v1/rent-to-rent?status=paid')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.booking_detail_id', $paidBooking['detail']->id)
            ->assertJsonPath('data.0.status', 'paid')
            ->assertJsonPath('summary.debt_count', 1);

        $this->getJson('/api/v1/rent-to-rent?status=open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.booking_detail_id', $openBooking['detail']->id);
    }

    public function test_owner_filter_options_are_limited_to_rent_to_rent_transactions_and_sorted(): void
    {
        $ctx = $this->context();
        $ownerZ = $this->owner($ctx['tenant']->id, false, 'Zulu Rental');
        $ownerA = $this->owner($ctx['tenant']->id, false, 'Alpha Rental');
        $this->owner($ctx['tenant']->id, false, 'Kosong Rental');

        $this->bookingWithDetail($ctx, $this->unit($ctx, $ownerZ->id)->id);
        $this->bookingWithDetail($ctx, $this->unit($ctx, $ownerA->id)->id);

        $response = $this->getJson('/api/v1/rent-to-rent')->assertOk();

        $this->assertSame(
            ['Alpha Rental', 'Zulu Rental'],
            collect($response->json('owner_options'))->pluck('nama')->all()
        );
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

    private function bookingWithDetail(array $ctx, int $unitId, array $bookingOverrides = [], array $detailOverrides = [], bool $syncDebt = true): array
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

        if ($syncDebt) {
            app(RentToRentService::class)->syncDetail($detail->fresh(['booking', 'unit.rentalOwner']));
        }

        return compact('booking', 'detail');
    }

    public function test_payment_notes_saving_auto_generation_and_report_filtering(): void
    {
        $ctx = $this->context();
        
        // Seed finance.account_mutation so the user can access reports
        \App\Models\RolePermission::create([
            'tenant_id' => $ctx['tenant']->id,
            'role' => 'finance',
            'permission_key' => 'finance.account_mutation',
        ]);

        $owner = $this->owner($ctx['tenant']->id, false, 'Owner Mitra');
        $unit = $this->unit($ctx, $owner->id, ['modal_1_hari' => 100000]);
        $account = PaymentAccount::create([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'nama_bank' => 'BCA',
            'nomor_rekening' => '123',
            'atas_nama' => 'DRENT',
            'is_active' => true,
        ]);

        $booking = $this->bookingWithDetail($ctx, $unit->id, detailOverrides: ['lama_sewa' => 2]);
        $debt = RentToRentDebt::where('booking_detail_id', $booking['detail']->id)->firstOrFail();

        // 1. Payment with manual note
        $this->postJson("/api/v1/rent-to-rent/{$debt->id}/payments", [
            'payment_account_id' => $account->id,
            'amount' => 50000,
            'paid_at' => '2026-05-18',
            'catatan' => 'Uang muka pertama',
        ])->assertOk();

        // Assert note is saved in RentToRentPayment
        $payment1 = RentToRentPayment::orderBy('id', 'desc')->firstOrFail();
        $this->assertSame('Uang muka pertama', $payment1->catatan);

        // Assert note is appended in PaymentAccountTransaction description
        $transaction1 = \App\Models\PaymentAccountTransaction::where('type', 'rent_to_rent_payment_out')->orderBy('id', 'desc')->firstOrFail();
        $this->assertStringContainsString('Uang muka pertama', $transaction1->description);

        // 2. Payment without note (should auto generate description)
        $this->postJson("/api/v1/rent-to-rent/{$debt->id}/payments", [
            'payment_account_id' => $account->id,
            'amount' => 60000,
            'paid_at' => '2026-05-19',
        ])->assertOk();

        $payment2 = RentToRentPayment::orderBy('id', 'desc')->firstOrFail();
        $this->assertNull($payment2->catatan);

        // 3. Get financial report and verify entries
        $response = $this->getJson('/api/v1/reports/monthly-finance?year=2026&month=5');
        $response->assertOk();

        $entries = $response->json('data.entries');
        
        // Assert only 'rent_to_rent_payment' entries are in the report, 'rent_to_rent_payment_out' is filtered out!
        $r2rEntries = collect($entries)->where('source_type', 'rent_to_rent_payment')->values();
        $r2rOutTransactions = collect($entries)->where('source_type', 'account_transaction')->where('type', 'rent_to_rent_payment_out')->values();

        $this->assertCount(2, $r2rEntries);
        $this->assertCount(0, $r2rOutTransactions);

        // Assert descriptions and references are correct
        // First entry: manual note
        $entry1 = $r2rEntries->firstWhere('source_id', $payment1->id);
        $this->assertSame('Uang muka pertama', $entry1['description']);
        $this->assertSame($booking['booking']->kode_booking, $entry1['reference']);

        // Second entry: auto generated note
        $entry2 = $r2rEntries->firstWhere('source_id', $payment2->id);
        $this->assertSame("Pembayaran rent-to-rent ke Owner Mitra untuk booking {$booking['booking']->kode_booking}", $entry2['description']);
        $this->assertSame($booking['booking']->kode_booking, $entry2['reference']);
    }
}
