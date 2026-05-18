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
