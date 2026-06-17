<?php

namespace Tests\Unit;

use App\Support\GenteTenantType;
use PHPUnit\Framework\TestCase;

class GenteTenantTypeTest extends TestCase
{
    public function test_tipos_canonicos(): void
    {
        $this->assertTrue(GenteTenantType::isValid(GenteTenantType::GLOBAL_SEMED));
        $this->assertFalse(GenteTenantType::isValid('INVALIDO'));
    }

    public function test_assert_valid_lanca(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GenteTenantType::assertValid('X');
    }
}
