<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\RentalOwner;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnitSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_search_supports_single_keyword_and_cross_field_tokens(): void
    {
        $ctx = $this->context();
        $otherBranch = Branch::create(['tenant_id' => $ctx['tenant']->id, 'name' => 'Other Branch']);
        $abigail = $this->owner($ctx['tenant']->id, 'Abigail Rental');
        $charlie = $this->owner($ctx['tenant']->id, 'Charlie Rental');

        $target = $this->unit($ctx, $abigail, [
            'merk' => 'Toyota',
            'tipe' => 'Avanza',
            'no_polisi' => 'B 1150 ABI',
        ]);
        $abigailBrio = $this->unit($ctx, $abigail, [
            'merk' => 'Honda',
            'tipe' => 'Brio',
            'no_polisi' => 'B 1150 ABG',
        ]);
        $abigailAvanza = $this->unit($ctx, $abigail, [
            'merk' => 'Toyota',
            'tipe' => 'Avanza',
            'no_polisi' => 'B 9988 ABI',
        ]);
        $charlieAvanza = $this->unit($ctx, $charlie, [
            'merk' => 'Toyota',
            'tipe' => 'Avanza',
            'no_polisi' => 'B 1150 CHR',
        ]);
        $otherBranchUnit = $this->unit([...$ctx, 'branch' => $otherBranch], $abigail, [
            'merk' => 'Toyota',
            'tipe' => 'Avanza',
            'no_polisi' => 'B 1150 OTH',
        ]);

        $abigailIds = $this->searchUnitIds('abigail');
        $this->assertContains($target->id, $abigailIds);
        $this->assertContains($abigailBrio->id, $abigailIds);
        $this->assertContains($abigailAvanza->id, $abigailIds);
        $this->assertNotContains($charlieAvanza->id, $abigailIds);
        $this->assertNotContains($otherBranchUnit->id, $abigailIds);

        $avanzaIds = $this->searchUnitIds('avanza');
        $this->assertContains($target->id, $avanzaIds);
        $this->assertContains($abigailAvanza->id, $avanzaIds);
        $this->assertContains($charlieAvanza->id, $avanzaIds);
        $this->assertNotContains($abigailBrio->id, $avanzaIds);
        $this->assertNotContains($otherBranchUnit->id, $avanzaIds);

        $multiTokenIds = $this->searchUnitIds('abigail avanz 1150');
        $this->assertSame([$target->id], $multiTokenIds);
    }

    public function test_unit_list_supports_filtering_units_without_modal(): void
    {
        $ctx = $this->context();
        $abigail = $this->owner($ctx['tenant']->id, 'Abigail Rental');

        // External units
        $completeUnit = $this->unit($ctx, $abigail, [
            'modal_1_hari' => 100000,
            'modal_all_in' => 150000,
        ]);
        
        $incompleteUnit1 = $this->unit($ctx, $abigail, [
            'modal_1_hari' => 0,
            'modal_all_in' => 150000,
        ]);

        $incompleteUnit2 = $this->unit($ctx, $abigail, [
            'modal_1_hari' => 100000,
            'modal_all_in' => 0,
        ]);

        // Internal units
        $completeInternalUnit = $this->unit($ctx, $abigail, [
            'rental_owner_id' => null,
            'modal_1_hari' => 100000,
            'modal_all_in' => 0, // not mandatory for internal
        ]);

        $incompleteInternalUnit = $this->unit($ctx, $abigail, [
            'rental_owner_id' => null,
            'modal_1_hari' => 0, // mandatory
            'modal_all_in' => 0,
        ]);

        // Get all units
        $allIds = collect($this->getJson('/api/v1/units?per_page=50')
            ->assertOk()
            ->json('data'))
            ->pluck('id')
            ->all();
        
        $this->assertContains($completeUnit->id, $allIds);
        $this->assertContains($incompleteUnit1->id, $allIds);
        $this->assertContains($incompleteUnit2->id, $allIds);
        $this->assertContains($completeInternalUnit->id, $allIds);
        $this->assertContains($incompleteInternalUnit->id, $allIds);

        // Get units without modal
        $withoutModalIds = collect($this->getJson('/api/v1/units?without_modal=true&per_page=50')
            ->assertOk()
            ->json('data'))
            ->pluck('id')
            ->all();

        $this->assertNotContains($completeUnit->id, $withoutModalIds);
        $this->assertContains($incompleteUnit1->id, $withoutModalIds);
        $this->assertContains($incompleteUnit2->id, $withoutModalIds);
        
        // Internal unit with modal_1_hari set (but modal_all_in 0) is COMPLETE (not without_modal)
        $this->assertNotContains($completeInternalUnit->id, $withoutModalIds);
        // Internal unit with modal_1_hari 0 is INCOMPLETE (without_modal)
        $this->assertContains($incompleteInternalUnit->id, $withoutModalIds);
    }

    private function searchUnitIds(string $search): array
    {
        return collect($this->getJson('/api/v1/units?search=' . urlencode($search) . '&per_page=50')
            ->assertOk()
            ->json('data'))
            ->pluck('id')
            ->all();
    }

    private function context(): array
    {
        $tenant = Tenant::create(['name' => 'DRENT', 'slug' => uniqid('drent-'), 'is_active' => true]);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Admin Branch',
            'email' => uniqid('admin-') . '@example.test',
            'password' => 'password',
            'role' => 'admin_branch',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        \App\Models\RolePermission::create([
            'tenant_id' => $tenant->id,
            'role' => 'admin_branch',
            'permission_key' => 'vehicle.unit',
        ]);

        return compact('tenant', 'branch', 'user');
    }

    private function owner(int $tenantId, string $name): RentalOwner
    {
        return RentalOwner::create([
            'tenant_id' => $tenantId,
            'nama' => $name,
            'kontak_1' => uniqid('08'),
            'is_owner' => true,
        ]);
    }

    private function unit(array $ctx, RentalOwner $owner, array $overrides = []): Unit
    {
        return Unit::create(array_merge([
            'tenant_id' => $ctx['tenant']->id,
            'branch_id' => $ctx['branch']->id,
            'rental_owner_id' => $owner->id,
            'merk' => 'Toyota',
            'tipe' => 'Avanza',
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
}
