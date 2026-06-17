<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EscalaTrabalhoTest extends TestCase
{
    public function test_regra_precedencia_absoluta_afastamento_sobre_turno(): void
    {
        DB::beginTransaction();
        try {
            $ctx = $this->seedEscalaFixtureComAfastamento(
                turnoDia: 15,
                afastInicioDia: 10,
                afastFimDia: 20
            );

            $response = $this
                ->actingAs($ctx['user'], 'web')
                ->getJson('/api/v3/escala-trabalho?mes='.$ctx['mes'].'&ano='.$ctx['ano'].'&setor_id='.$ctx['setor_id'].'&per_page=200');

            $response->assertOk();
            $linha = $this->encontrarLinhaFuncionario($response->json('escala') ?? [], $ctx['funcionario_id']);
            $this->assertNotNull($linha, 'Funcionário da fixture não foi retornado no payload da escala.');

            $dia15 = $linha['dias']['15'] ?? $linha['dias'][15] ?? null;
            $this->assertNotNull($dia15, 'Dia 15 não foi retornado para o funcionário no payload.');
            $this->assertTrue((bool) ($dia15['bloqueada_por_afastamento'] ?? false));
            $this->assertSame('LM', strtoupper((string) ($dia15['afastamento']['sigla'] ?? '')));
        } finally {
            DB::rollBack();
        }
    }

    public function test_protecao_mutacao_post_bloqueia_dia_com_afastamento_ativo(): void
    {
        DB::beginTransaction();
        try {
            $ctx = $this->seedEscalaFixtureComAfastamento(
                turnoDia: 15,
                afastInicioDia: 10,
                afastFimDia: 20
            );

            $dataAlvo = sprintf('%04d-%02d-15', $ctx['ano'], $ctx['mes']);
            $response = $this
                ->actingAs($ctx['user'], 'web')
                ->postJson('/api/v3/escala-trabalho', [
                    'funcionario_id' => $ctx['funcionario_id'],
                    'data' => $dataAlvo,
                    'turno' => 'V',
                ]);

            $this->assertContains($response->getStatusCode(), [403, 422]);
            $erro = (string) ($response->json('erro') ?? '');
            $this->assertStringContainsStringIgnoringCase('bloqueado por afastamento', $erro);
        } finally {
            DB::rollBack();
        }
    }

    public function test_regra_compliance_licenca_medica_acima_15_dias(): void
    {
        DB::beginTransaction();
        try {
            $ctx = $this->seedEscalaFixtureComAfastamento(
                turnoDia: 15,
                afastInicioDia: 1,
                afastFimDia: 16
            );

            $response = $this
                ->actingAs($ctx['user'], 'web')
                ->getJson('/api/v3/escala-trabalho?mes='.$ctx['mes'].'&ano='.$ctx['ano'].'&setor_id='.$ctx['setor_id'].'&per_page=200');

            $response->assertOk();
            $linha = $this->encontrarLinhaFuncionario($response->json('escala') ?? [], $ctx['funcionario_id']);
            $this->assertNotNull($linha);
            $dia1 = $linha['dias']['1'] ?? $linha['dias'][1] ?? null;
            $this->assertNotNull($dia1, 'Dia 1 não foi retornado no payload.');
            $this->assertTrue((bool) ($dia1['afastamento']['ultrapassa_15_dias'] ?? false));
        } finally {
            DB::rollBack();
        }
    }

    public function test_calculo_totais_isolamento_dias_bloqueados_por_afastamento(): void
    {
        DB::beginTransaction();
        try {
            $ctx = $this->seedEscalaFixtureComAfastamento(
                turnoDia: 15,
                afastInicioDia: 10,
                afastFimDia: 20
            );

            $response = $this
                ->actingAs($ctx['user'], 'web')
                ->getJson('/api/v3/escala-trabalho?mes='.$ctx['mes'].'&ano='.$ctx['ano'].'&setor_id='.$ctx['setor_id'].'&per_page=200');

            $response->assertOk();
            $linha = $this->encontrarLinhaFuncionario($response->json('escala') ?? [], $ctx['funcionario_id']);
            $this->assertNotNull($linha);

            $dia15 = $linha['dias']['15'] ?? $linha['dias'][15] ?? null;
            $this->assertNotNull($dia15);
            $this->assertTrue((bool) ($dia15['bloqueada_por_afastamento'] ?? false));
            $this->assertSame('M', (string) ($dia15['turno_planejado'] ?? ''), 'Turno planejado deve permanecer como referência histórica.');

            $dias = is_array($linha['dias'] ?? null) ? $linha['dias'] : [];
            $totalEfetivo = 0;
            foreach ($dias as $dia) {
                $bloqueada = (bool) ($dia['bloqueada_por_afastamento'] ?? false);
                $turno = strtoupper(trim((string) ($dia['turno'] ?? '')));
                if (! $bloqueada && $turno !== '' && $turno !== 'F') {
                    $totalEfetivo++;
                }
            }
            $this->assertSame(0, $totalEfetivo, 'Dias bloqueados por afastamento não podem compor total de produtividade.');
        } finally {
            DB::rollBack();
        }
    }

    private function seedEscalaFixtureComAfastamento(int $turnoDia, int $afastInicioDia, int $afastFimDia): array
    {
        foreach (['USUARIO', 'UNIDADE', 'SETOR', 'FUNCIONARIO', 'LOTACAO', 'ESCALA', 'DETALHE_ESCALA', 'DETALHE_ESCALA_ITEM', 'TURNO', 'AFASTAMENTO', 'TABELA_GENERICA'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->markTestSkipped("Tabela {$table} inexistente neste ambiente de teste.");
            }
        }

        $base = Carbon::now()->addMonthNoOverflow()->startOfMonth();
        $ano = (int) $base->format('Y');
        $mes = (int) $base->format('m');
        $competencia = sprintf('%04d-%02d', $ano, $mes);

        $setor = DB::table('SETOR')
            ->whereNotNull('UNIDADE_ID')
            ->select('SETOR_ID', 'UNIDADE_ID')
            ->orderBy('SETOR_ID')
            ->first();
        if (! $setor) {
            $this->markTestSkipped('Não há SETOR disponível para montar fixture da escala.');
        }

        $temLotacaoFim = Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM');
        $temFuncionarioFim = Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM');
        $hoje = now()->toDateString();
        $funcionarioId = (int) (DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($join) use ($temLotacaoFim) {
                $join->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
                if ($temLotacaoFim) {
                    $join->whereNull('l.LOTACAO_DATA_FIM');
                }
            })
            ->where('l.SETOR_ID', (int) $setor->SETOR_ID)
            ->whereNotNull('l.LOTACAO_ID')
            ->when($temFuncionarioFim, fn ($q) => $q->where(function ($w) use ($hoje) {
                $w->whereNull('f.FUNCIONARIO_DATA_FIM')
                    ->orWhere('f.FUNCIONARIO_DATA_FIM', '>', $hoje);
            }))
            ->orderBy('p.PESSOA_NOME')
            ->value('f.FUNCIONARIO_ID') ?? 0);
        if ($funcionarioId <= 0) {
            $this->markTestSkipped('Não há FUNCIONARIO disponível para montar fixture da escala.');
        }

        if (! DB::table('LOTACAO')->where('FUNCIONARIO_ID', $funcionarioId)->where('SETOR_ID', (int) $setor->SETOR_ID)->exists()) {
            DB::table('LOTACAO')->insert([
                'FUNCIONARIO_ID' => $funcionarioId,
                'SETOR_ID' => (int) $setor->SETOR_ID,
            ]);
        }

        $userId = (int) DB::table('USUARIO')->insertGetId([
            'USUARIO_LOGIN' => 'escala.teste+'.uniqid().'@semed.local',
            'USUARIO_SENHA' => Hash::make('Teste@123'),
            'USUARIO_NOME' => 'Teste Escala Ausencia',
            'USUARIO_ATIVO' => 1,
            'USUARIO_PRIMEIRO_ACESSO' => 0,
            'USUARIO_ALTERAR_SENHA' => 0,
        ]);
        DB::table('USUARIO_UNIDADE')->insert([
            'USUARIO_ID' => $userId,
            'UNIDADE_ID' => (int) $setor->UNIDADE_ID,
            'USUARIO_UNIDADE_ATIVO' => 1,
        ]);

        DB::table('TABELA_GENERICA')->updateOrInsert(
            ['TABELA_ID' => 5, 'COLUNA_ID' => 8],
            ['COLUNA_DESCRICAO' => 'Licença Médica', 'DESCRICAO' => 'Licença Médica']
        );

        $turnoId = DB::table('TURNO')->where('TURNO_SIGLA', 'M')->value('TURNO_ID');
        if (! $turnoId) {
            $turnoId = DB::table('TURNO')->insertGetId([
                'TURNO_SIGLA' => 'M',
                'TURNO_NOME' => 'Matutino',
            ]);
        }

        $escala = DB::table('ESCALA')
            ->where('ESCALA_COMPETENCIA', $competencia)
            ->where('SETOR_ID', (int) $setor->SETOR_ID)
            ->first();
        if (! $escala) {
            $escalaId = DB::table('ESCALA')->insertGetId([
                'ESCALA_COMPETENCIA' => $competencia,
                'SETOR_ID' => (int) $setor->SETOR_ID,
                'ESCALA_STATUS' => 'RASCUNHO',
            ]);
        } else {
            $escalaId = (int) $escala->ESCALA_ID;
        }

        $detalhe = DB::table('DETALHE_ESCALA')
            ->where('ESCALA_ID', $escalaId)
            ->where('FUNCIONARIO_ID', $funcionarioId)
            ->first();
        if (! $detalhe) {
            $detalheId = DB::table('DETALHE_ESCALA')->insertGetId([
                'ESCALA_ID' => $escalaId,
                'FUNCIONARIO_ID' => $funcionarioId,
            ]);
        } else {
            $detalheId = (int) $detalhe->DETALHE_ESCALA_ID;
        }

        $diaTurno = sprintf('%04d-%02d-%02d', $ano, $mes, $turnoDia);
        DB::table('DETALHE_ESCALA_ITEM')->updateOrInsert(
            ['DETALHE_ESCALA_ID' => $detalheId, 'DETALHE_ESCALA_ITEM_DATA' => $diaTurno],
            ['TURNO_ID' => (int) $turnoId]
        );

        DB::table('AFASTAMENTO')->insert([
            'FUNCIONARIO_ID' => $funcionarioId,
            'AFASTAMENTO_DATA_INICIO' => sprintf('%04d-%02d-%02d', $ano, $mes, $afastInicioDia),
            'AFASTAMENTO_DATA_FIM' => sprintf('%04d-%02d-%02d', $ano, $mes, $afastFimDia),
            'AFASTAMENTO_TIPO' => 8,
        ]);

        return [
            'ano' => $ano,
            'mes' => $mes,
            'setor_id' => (int) $setor->SETOR_ID,
            'funcionario_id' => $funcionarioId,
            'user' => Usuario::findOrFail($userId),
        ];
    }

    private function encontrarLinhaFuncionario(array $escala, int $funcionarioId): ?array
    {
        foreach ($escala as $linha) {
            if ((int) ($linha['funcionario_id'] ?? 0) === $funcionarioId) {
                return $linha;
            }
        }

        return null;
    }
}

