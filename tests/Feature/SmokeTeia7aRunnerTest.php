<?php

namespace Tests\Feature;

use App\Services\Smoke\SmokeTeiaFolhaOptions;
use App\Services\Smoke\SmokeTeiaFolhaRunner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SmokeTeia7aRunnerTest extends TestCase
{
    public function test_smoke_teia_7a_runner_executa_sem_excecao(): void
    {
        foreach (['FUNCIONARIO', 'FOLHA'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("Tabela {$t} inexistente neste ambiente.");
            }
        }

        DB::beginTransaction();
        try {
            $runner = new SmokeTeiaFolhaRunner();
            $rows = $runner->run(new SmokeTeiaFolhaOptions());
            $this->assertNotEmpty($rows);
            foreach ($rows as $r) {
                $this->assertArrayHasKey('fluxo', $r);
                $this->assertArrayHasKey('status', $r);
                $this->assertContains($r['status'], ['pass', 'fail', 'skip'], 'Status inválido: '.$r['status']);
                $this->assertArrayHasKey('detalhe', $r);
                $this->assertArrayHasKey('onde_rompeu', $r);
            }
        } finally {
            DB::rollBack();
        }
    }
}
