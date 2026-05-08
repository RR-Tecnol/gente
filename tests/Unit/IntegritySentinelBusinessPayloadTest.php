<?php

namespace Tests\Unit;

use App\Support\IntegritySentinelService;
use Tests\TestCase;

class IntegritySentinelBusinessPayloadTest extends TestCase
{
    public function test_detects_contingency_flags_in_payload(): void
    {
        $payload = [
            'fallback' => true,
            'erro' => 'Integração indisponível no momento',
        ];

        $this->assertTrue(IntegritySentinelService::isPayloadContingency($payload));
    }

    public function test_detects_safe_mode_source_as_contingency(): void
    {
        $payload = [
            'source' => 'safe_mode',
            'data' => [],
        ];

        $this->assertTrue(IntegritySentinelService::isPayloadContingency($payload));
    }

    public function test_accepts_real_payload_without_contingency(): void
    {
        $payload = [
            'fallback' => false,
            'progressoes' => [],
        ];

        $this->assertFalse(IntegritySentinelService::isPayloadContingency($payload));
    }
}

