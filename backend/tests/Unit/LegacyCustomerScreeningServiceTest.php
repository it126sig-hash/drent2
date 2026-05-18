<?php

namespace Tests\Unit;

use App\Services\LegacyCustomerScreeningService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class LegacyCustomerScreeningServiceTest extends TestCase
{
    public function test_normalizes_indonesian_phone_numbers(): void
    {
        $service = new LegacyCustomerScreeningService();

        $this->assertSame('081234567890', $this->callPrivate($service, 'normalizePhone', ['+62 812-3456-7890']));
        $this->assertSame('081234567890', $this->callPrivate($service, 'normalizePhone', ['0812.3456.7890']));
        $this->assertNull($this->callPrivate($service, 'normalizePhone', ['-']));
        $this->assertNull($this->callPrivate($service, 'normalizePhone', ['022']));
    }

    public function test_code_only_owner_match_stays_manual_when_name_and_phone_do_not_match(): void
    {
        $service = new LegacyCustomerScreeningService();

        $customer = [
            'kode_pelanggan' => 'PLG-1',
            'nama_pelanggan' => 'Budi Santoso',
            'status' => 'Member',
            'kode_pemilik' => 'PML-1',
            'primary_phone' => '081111111111',
            'transaction_count' => 0,
            'normalized_name' => 'budisantoso',
            'name_tokens' => ['budi', 'santoso'],
        ];
        $owner = [
            'kode_pemilik' => 'PML-1',
            'nama_pemilik' => 'Abigail Rental',
            'primary_phone' => '082222222222',
            'unit_count' => 10,
            'normalized_name' => 'abigail',
            'name_tokens' => ['abigail'],
        ];

        $result = $this->callPrivate($service, 'scoreOwnerCandidate', [$customer, $owner, ['kode_pemilik'], 80]);

        $this->assertSame('manual_review_owner', $result['decision']);
        $this->assertSame(78, $result['score']);
    }

    public function test_phone_and_strong_name_match_becomes_auto_owner(): void
    {
        $service = new LegacyCustomerScreeningService();

        $customer = [
            'kode_pelanggan' => 'PLG-2',
            'nama_pelanggan' => 'Bastian Rental',
            'status' => 'Umum',
            'kode_pemilik' => null,
            'primary_phone' => '081221896676',
            'transaction_count' => 3,
            'normalized_name' => 'bastian',
            'name_tokens' => ['bastian'],
        ];
        $owner = [
            'kode_pemilik' => 'PML-2',
            'nama_pemilik' => 'Bastian Rental',
            'primary_phone' => '081221896676',
            'unit_count' => 5,
            'normalized_name' => 'bastian',
            'name_tokens' => ['bastian'],
        ];

        $result = $this->callPrivate($service, 'scoreOwnerCandidate', [$customer, $owner, ['phone', 'exact_name'], 80]);

        $this->assertSame('auto_owner', $result['decision']);
        $this->assertSame(99, $result['score']);
    }

    private function callPrivate(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($method);

        return $method->invokeArgs($object, $arguments);
    }
}
