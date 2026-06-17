<?php

namespace Tests\Unit;

use App\Services\Jornada\JornadaRegraParametros;
use PHPUnit\Framework\TestCase;

class JornadaRegraParametrosPureTest extends TestCase
{
    public function test_valor_adicional_3h_90_1_terco(): void
    {
        $v = JornadaRegraParametros::valorAdicionalHoraFracionada(3.0, 90.0, 1 / 3);
        $this->assertEquals(90.0, $v);
    }
}
