<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberExtensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_member_status(): void
    {
        $ctx = $this->context('superadmin');
        $customer = $this->customer($ctx, 'Normal', 'Budi Normal');
        
        $member = Member::create([
            'tenant_id' => $ctx['tenant']->id,
            'customer_id' => $customer->id,
            'status_member' => 'Pending',
        ]);

        $response = $this->patchJson("/api/v1/members/{$member->id}/status", [
            'status_member' => 'Aktif',
        ]);

        $response->assertOk();
        $this->assertEquals('Aktif', $member->fresh()->status_member);
        $this->assertNotNull($member->fresh()->id_member);
    }

    public function test_can_extend_member_and_view_history(): void
    {
        $ctx = $this->context('superadmin');
        $customer = $this->customer($ctx, 'Normal', 'Maya Member');
        
        $member = Member::create([
            'tenant_id' => $ctx['tenant']->id,
            'customer_id' => $customer->id,
            'status_member' => 'Aktif',
            'tanggal_exp' => '2026-12-31',
        ]);

        $response = $this->postJson("/api/v1/members/{$member->id}/extend", [
            'new_exp_date' => '2027-12-31',
            'catatan' => 'Perpanjang tahunan',
        ]);

        $response->assertCreated();
        $this->assertEquals('2027-12-31', $member->fresh()->tanggal_exp->format('Y-m-d'));

        // Test list history
        $responseHistory = $this->getJson("/api/v1/members/{$member->id}/extensions");
        $responseHistory->assertOk();
        $responseHistory->assertJsonCount(1, 'data');
        $responseHistory->assertJsonPath('data.0.catatan', 'Perpanjang tahunan');
        $responseHistory->assertJsonPath('data.0.new_exp_date', '2027-12-31');
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
}
