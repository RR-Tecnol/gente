<?php

namespace Tests\Feature;

use Tests\TestCase;

class ShadowSmokeE2eFixtureTest extends TestCase
{
    public function test_fixture_satisfaz_snapshot_legado_e_canonico(): void
    {
        $base = (string) (realpath(__DIR__ . '/../fixtures/shadow_smoke_e2e/2026-04'));
        $this->assertNotFalse($base, 'Diretório de fixture inexistente');

        $this->artisan('shadow:snapshot-validar', [
            'competencia' => '2026-04',
            '--snapshot-dir' => $base,
        ])->assertExitCode(0);

        $this->artisan('shadow:snapshot-canonico-validar', [
            'competencia' => '2026-04',
            '--snapshot-dir' => $base,
            '--json' => true,
        ])->assertExitCode(0);
    }
}
