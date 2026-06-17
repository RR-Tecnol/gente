<?php

namespace Database\Seeders;

use App\Domain\Escala\EscalaWorkflowStatus;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SidebarCoverageSeeder extends Seeder
{
    public function run(): void
    {
        $funcionarios = $this->funcionariosAtivos();
        if ($funcionarios->isEmpty()) {
            $this->command->warn('SidebarCoverageSeeder: nenhum funcionário ativo para seed de cobertura.');
            return;
        }

        $adminId = DB::table('USUARIO')->where('USUARIO_LOGIN', 'admin')->value('USUARIO_ID');
        $this->garantirVinculoAdmin($adminId, $funcionarios);
        $this->seedPontoConfig($funcionarios);
        $this->seedRegistroPonto($funcionarios);
        $this->seedBancoHoras($funcionarios, $adminId);
        $this->seedBancoHorasEquipeMesmoSetor($funcionarios, $adminId);
        $this->seedJornadaLedgerCriticosEquipe($funcionarios, $adminId);
        $this->seedAdminPontoExecutivo($adminId);
        $this->seedHoraExtraEPlantao($funcionarios, $adminId);
        $this->seedTurnosEscala();
        $this->seedEscalaESubstituicoes($funcionarios);
        $this->seedDeclaracoes($funcionarios);
        $this->seedFolha2026($funcionarios);
        $this->seedOrcamento();
        $this->seedTesouraria();
        $this->seedReceita();

        $this->command->info('✅ SidebarCoverageSeeder: cobertura mínima de dados aplicada para abas críticas do sidebar.');
    }

    private function funcionariosAtivos()
    {
        $query = DB::table('FUNCIONARIO')->select('FUNCIONARIO_ID', 'USUARIO_ID');
        if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM')) {
            $query->whereNull('FUNCIONARIO_DATA_FIM');
        }

        return $query->limit(25)->get();
    }

    private function garantirVinculoAdmin($adminId, $funcionarios): void
    {
        if (!$adminId || !Schema::hasColumn('FUNCIONARIO', 'USUARIO_ID')) {
            return;
        }

        $jaVinculado = DB::table('FUNCIONARIO')->where('USUARIO_ID', $adminId)->exists();
        if ($jaVinculado) {
            return;
        }

        // Preferência explícita: Ana Cristina Barros (CPF 47026653038)
        $funcionarioAdmin = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->where('p.PESSOA_CPF_NUMERO', '47026653038')
            ->select('f.FUNCIONARIO_ID')
            ->first();
        if (!$funcionarioAdmin) {
            $funcionarioAdmin = $funcionarios->first();
        }
        if ($funcionarioAdmin) {
            DB::table('FUNCIONARIO')
                ->where('FUNCIONARIO_ID', $funcionarioAdmin->FUNCIONARIO_ID)
                ->update(['USUARIO_ID' => $adminId]);
            $this->command->info('↳ vínculo admin->funcionário ajustado para testes locais.');
        }
    }

    private function seedPontoConfig($funcionarios): void
    {
        if (!Schema::hasTable('PONTO_CONFIG_FUNCIONARIO')) {
            return;
        }

        $colunas = Schema::getColumnListing('PONTO_CONFIG_FUNCIONARIO');
        foreach ($funcionarios->take(20) as $f) {
            $data = [];
            if (in_array('REGIME', $colunas, true))
                $data['REGIME'] = '4_batidas';
            if (in_array('HORA_ENTRADA', $colunas, true))
                $data['HORA_ENTRADA'] = '08:00';
            if (in_array('HORA_SAIDA', $colunas, true))
                $data['HORA_SAIDA'] = '17:00';
            if (in_array('TOLERANCIA', $colunas, true))
                $data['TOLERANCIA'] = 15;
            if (in_array('INTERVALO_ALMOCO', $colunas, true))
                $data['INTERVALO_ALMOCO'] = 60;
            if (in_array('updated_at', $colunas, true))
                $data['updated_at'] = now();
            if (in_array('created_at', $colunas, true))
                $data['created_at'] = now();

            DB::table('PONTO_CONFIG_FUNCIONARIO')->updateOrInsert(
                ['FUNCIONARIO_ID' => $f->FUNCIONARIO_ID],
                $data
            );
        }
    }

    private function seedRegistroPonto($funcionarios): void
    {
        if (!Schema::hasTable('REGISTRO_PONTO')) {
            return;
        }

        $colunas = Schema::getColumnListing('REGISTRO_PONTO');
        $origemCol = in_array('REGISTRO_ORIGEM', $colunas, true);
        $tipoCol = in_array('REGISTRO_TIPO', $colunas, true);
        $dataHoraCol = in_array('REGISTRO_DATA_HORA', $colunas, true);
        if (!$tipoCol || !$dataHoraCol) {
            return;
        }

        $inicio = Carbon::now()->startOfMonth();
        $fim = Carbon::now()->copy()->endOfMonth();

        foreach ($funcionarios->take(10) as $f) {
            $jaTemNoMes = DB::table('REGISTRO_PONTO')
                ->where('FUNCIONARIO_ID', $f->FUNCIONARIO_ID)
                ->whereBetween('REGISTRO_DATA_HORA', [$inicio->toDateTimeString(), $fim->toDateTimeString()])
                ->exists();
            if ($jaTemNoMes) {
                continue;
            }

            for ($d = 1; $d <= 10; $d++) {
                $dia = Carbon::now()->startOfMonth()->addDays($d - 1);
                if ($dia->isWeekend())
                    continue;

                foreach ([
                    ['entrada', '08:00:00'],
                    ['saida_alm', '12:00:00'],
                    ['ret_alm', '13:00:00'],
                    ['saida', '17:00:00'],
                ] as [$tipo, $hora]) {
                    $insert = [
                        'FUNCIONARIO_ID' => $f->FUNCIONARIO_ID,
                        'REGISTRO_DATA_HORA' => $dia->toDateString() . ' ' . $hora,
                        'REGISTRO_TIPO' => $tipo,
                    ];
                    if ($origemCol)
                        $insert['REGISTRO_ORIGEM'] = 'SEED';
                    DB::table('REGISTRO_PONTO')->insert($insert);
                }
            }
        }
    }

    private function seedBancoHoras($funcionarios, $adminId): void
    {
        if (!Schema::hasTable('BANCO_HORAS')) {
            Schema::create('BANCO_HORAS', function (Blueprint $table) {
                $table->increments('BANCO_HORAS_ID');
                $table->integer('FUNCIONARIO_ID');
                $table->string('COMPETENCIA', 7)->nullable(); // YYYY-MM
                $table->decimal('HORAS_CREDITADAS', 10, 2)->default(0);
                $table->decimal('HORAS_DEBITADAS', 10, 2)->default(0);
                $table->string('TIPO', 20)->nullable();
                $table->string('OBSERVACAO', 255)->nullable();
                $table->integer('REGISTRADO_POR')->nullable();
                $table->timestamps();
            });
        }

        foreach ($funcionarios->take(15) as $f) {
            for ($m = 0; $m < 4; $m++) {
                $comp = now()->subMonths($m)->format('Y-m');
                $jaTem = DB::table('BANCO_HORAS')
                    ->where('FUNCIONARIO_ID', $f->FUNCIONARIO_ID)
                    ->where('COMPETENCIA', $comp)
                    ->exists();
                if ($jaTem) {
                    continue;
                }

                DB::table('BANCO_HORAS')->insert([
                    'FUNCIONARIO_ID' => $f->FUNCIONARIO_ID,
                    'COMPETENCIA' => $comp,
                    'HORAS_CREDITADAS' => 12 - ($m * 1.5),
                    'HORAS_DEBITADAS' => $m === 2 ? 8 : ($m === 3 ? 4 : 2),
                    'TIPO' => $m % 2 === 0 ? 'CREDITO' : 'COMPENSACAO',
                    'OBSERVACAO' => 'Seed cobertura sidebar',
                    'REGISTRADO_POR' => $adminId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * GET /api/v3/banco-horas/equipe lista colegas do MESMO setor (exclui o logado) com saldo no mês.
     * Sem vários servidores lotados no mesmo setor + BANCO_HORAS na competência, a tela fica vazia.
     * Garante: lotação ativa apontando para um setor comum + linhas em BANCO_HORAS (2026-04) com saldos +/−/0 para filtros.
     */
    private function seedBancoHorasEquipeMesmoSetor($funcionarios, $adminId): void
    {
        if (!Schema::hasTable('BANCO_HORAS') || !Schema::hasTable('SETOR')) {
            return;
        }

        $setorId = DB::table('SETOR')
            ->when(Schema::hasColumn('SETOR', 'SETOR_ATIVO'), function ($q) {
                $q->where('SETOR_ATIVO', 1);
            })
            ->orderBy('SETOR_ID')
            ->value('SETOR_ID');
        if (!$setorId || !Schema::hasTable('LOTACAO')) {
            $this->command->warn('SidebarCoverageSeeder: seed equipe banco de horas ignorado (SETOR/LOTACAO).');
            return;
        }

        $adminFuncionarioId = null;
        if ($adminId && Schema::hasColumn('FUNCIONARIO', 'USUARIO_ID')) {
            $adminFuncionarioId = DB::table('FUNCIONARIO')
                ->where('USUARIO_ID', $adminId)
                ->value('FUNCIONARIO_ID');
        }

        $alvo = $funcionarios;
        if ($adminFuncionarioId) {
            $adminObj = $funcionarios->firstWhere('FUNCIONARIO_ID', $adminFuncionarioId)
                ?? (object) ['FUNCIONARIO_ID' => $adminFuncionarioId, 'USUARIO_ID' => $adminId];
            $alvo = collect([$adminObj])->merge($funcionarios);
        }
        $alvo = $alvo
            ->unique(fn($f) => (int) $f->FUNCIONARIO_ID)
            ->values()
            ->take(8);
        $lotCols = Schema::getColumnListing('LOTACAO');
        $temFim = Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM');
        $temInicio = Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_INICIO');

        foreach ($alvo as $f) {
            $fid = (int) $f->FUNCIONARIO_ID;
            $afetados = DB::table('LOTACAO')
                ->where('FUNCIONARIO_ID', $fid)
                ->when($temFim, fn ($q) => $q->whereNull('LOTACAO_DATA_FIM'))
                ->update(['SETOR_ID' => $setorId]);

            if ($afetados === 0) {
                $row = ['FUNCIONARIO_ID' => $fid, 'SETOR_ID' => $setorId];
                if ($temInicio) {
                    $row['LOTACAO_DATA_INICIO'] = Carbon::now()->subYear()->toDateString();
                }
                if ($temFim) {
                    $row['LOTACAO_DATA_FIM'] = null;
                }
                if (Schema::hasColumn('LOTACAO', 'LOTACAO_ATIVO')) {
                    $row['LOTACAO_ATIVO'] = 1;
                }
                DB::table('LOTACAO')->insert($row);
            }
        }

        if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM')) {
            DB::table('FUNCIONARIO')
                ->whereIn('FUNCIONARIO_ID', $alvo->pluck('FUNCIONARIO_ID'))
                ->update(['FUNCIONARIO_DATA_FIM' => null]);
        }

        $compAbril = '2026-04';
        $perfis = [
            [12, 2],
            [2, 10],
            [5, 5],
            [9, 2],
            [1, 4],
            [6, 2],
            [0, 0],
            [7, 1],
        ];

        $i = 0;
        foreach ($alvo as $f) {
            [$cred, $deb] = $perfis[$i % count($perfis)];
            $i++;
            $payload = [
                'HORAS_CREDITADAS' => $cred,
                'HORAS_DEBITADAS' => $deb,
                'TIPO' => 'CREDITO',
                'OBSERVACAO' => 'Seed cobertura — equipe banco de horas (Abr/2026)',
                'REGISTRADO_POR' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            DB::table('BANCO_HORAS')->updateOrInsert(
                ['FUNCIONARIO_ID' => $f->FUNCIONARIO_ID, 'COMPETENCIA' => $compAbril],
                $payload
            );
        }

        $this->command->info('↳ Banco de Horas (equipe): ' . $alvo->count() . ' servidores no setor ' . $setorId . ', competência ' . $compAbril . ' com saldos variados.');
    }

    private function seedJornadaLedgerCriticosEquipe($funcionarios, $adminId): void
    {
        if (!Schema::hasTable('JORNADA_LEDGER') || !Schema::hasTable('LOTACAO') || !Schema::hasTable('SETOR')) {
            return;
        }
        $adminUsuarioId = DB::table('USUARIO')->where('USUARIO_LOGIN', 'admin')->value('USUARIO_ID');
        if (!$adminUsuarioId) {
            return;
        }
        $adminFuncionarioId = DB::table('FUNCIONARIO')->where('USUARIO_ID', $adminUsuarioId)->value('FUNCIONARIO_ID');
        if (!$adminFuncionarioId) {
            return;
        }
        $setorAdmin = DB::table('LOTACAO')
            ->where('FUNCIONARIO_ID', $adminFuncionarioId)
            ->whereNull('LOTACAO_DATA_FIM')
            ->value('SETOR_ID');
        if (!$setorAdmin) {
            return;
        }

        $colegas = DB::table('LOTACAO as l')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'l.FUNCIONARIO_ID')
            ->where('l.SETOR_ID', $setorAdmin)
            ->whereNull('l.LOTACAO_DATA_FIM')
            ->where('f.FUNCIONARIO_ID', '<>', $adminFuncionarioId)
            ->limit(6)
            ->pluck('f.FUNCIONARIO_ID')
            ->values();

        if ($colegas->isEmpty()) {
            return;
        }

        $competencia = now()->format('Y-m');
        $dataBase = now()->startOfMonth()->addDays(4);
        $perfis = [
            ['trab' => 240, 'meta' => 480], // -4h
            ['trab' => 180, 'meta' => 480], // -5h
            ['trab' => 300, 'meta' => 480], // -3h
            ['trab' => 420, 'meta' => 480], // -1h
            ['trab' => 460, 'meta' => 480], // -20min
            ['trab' => 520, 'meta' => 480], // +40min
        ];

        foreach ($colegas as $idx => $fid) {
            $perfil = $perfis[$idx % count($perfis)];
            $dia = $dataBase->copy()->addDays($idx)->toDateString();
            $existe = DB::table('JORNADA_LEDGER')
                ->where('FUNCIONARIO_ID', $fid)
                ->where('COMPETENCIA', $competencia)
                ->where('JORNADA_DATA', $dia)
                ->exists();
            if ($existe) {
                continue;
            }
            $delta = (int) $perfil['trab'] - (int) $perfil['meta'];
            $cred = $delta > 0 ? round($delta / 60, 2) : 0;
            $deb = $delta < 0 ? round(abs($delta) / 60, 2) : 0;
            $detalhe = [
                'funcionario_id' => (int) $fid,
                'seed' => true,
                'min_trabalhados' => (int) $perfil['trab'],
                'min_meta' => (int) $perfil['meta'],
                'min_delta' => $delta,
            ];
            $hash = hash('sha256', json_encode($detalhe, JSON_UNESCAPED_UNICODE) . '|1|');
            DB::table('JORNADA_LEDGER')->insert([
                'FUNCIONARIO_ID' => (int) $fid,
                'COMPETENCIA' => $competencia,
                'JORNADA_DATA' => $dia,
                'LANCAMENTO_TIPO' => $delta >= 0 ? 'credito' : 'debito',
                'MINUTOS_TRABALHADOS' => (int) $perfil['trab'],
                'MINUTOS_META' => (int) $perfil['meta'],
                'MINUTOS_DELTA' => $delta,
                'HORAS_CREDITADAS' => $cred,
                'HORAS_DEBITADAS' => $deb,
                'SALDO_HORAS' => round($delta / 60, 2),
                'VERSAO' => 1,
                'ORIGEM' => 'seed_sidebar',
                'MOTIVO' => 'seed_criticos_equipe',
                'DETALHE' => json_encode($detalhe, JSON_UNESCAPED_UNICODE),
                'HASH_AUDITORIA' => $hash,
                'GERADO_POR_USUARIO_ID' => (int) $adminId,
                'GERADO_EM' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Garante cenário "profissional" para o usuário admin em Ponto/Banco de Horas:
     * - jornada mensal consistente (horas esperadas, trabalhadas e saldo não nulo),
     * - distribuição realista de dias com crédito e débito.
     */
    private function seedAdminPontoExecutivo($adminId): void
    {
        if (
            !$adminId ||
            !Schema::hasTable('FUNCIONARIO') ||
            !Schema::hasTable('JORNADA_LEDGER')
        ) {
            return;
        }

        $adminFuncionarioId = DB::table('FUNCIONARIO')
            ->where('USUARIO_ID', $adminId)
            ->value('FUNCIONARIO_ID');
        if (!$adminFuncionarioId) {
            return;
        }

        $competencia = now()->format('Y-m');
        $colsLedger = Schema::getColumnListing('JORNADA_LEDGER');
        $temHashAnterior = in_array('HASH_ANTERIOR', $colsLedger, true);
        $inicioMes = now()->startOfMonth();
        $diasUteis = [];
        for ($d = 0; $d < 22; $d++) {
            $dia = $inicioMes->copy()->addDays($d);
            if (!$dia->isWeekend()) {
                $diasUteis[] = $dia;
            }
            if (count($diasUteis) >= 18) {
                break;
            }
        }
        if (empty($diasUteis)) {
            return;
        }

        // 18 dias x 8h = 144h esperadas (meta do painel).
        $padraoMinutos = [480, 465, 510, 450, 480, 495, 470, 500, 455, 480, 490, 460, 520, 430, 480, 505, 470, 490];
        $hashAnterior = null;

        foreach ($diasUteis as $idx => $dia) {
            $meta = 480;
            $trabalhados = $padraoMinutos[$idx % count($padraoMinutos)];
            $delta = $trabalhados - $meta;
            $cred = $delta > 0 ? round($delta / 60, 2) : 0;
            $deb = $delta < 0 ? round(abs($delta) / 60, 2) : 0;
            $detalhe = [
                'funcionario_id' => (int) $adminFuncionarioId,
                'seed' => true,
                'perfil' => 'admin_executivo',
                'min_trabalhados' => (int) $trabalhados,
                'min_meta' => (int) $meta,
                'min_delta' => (int) $delta,
            ];
            $hashAtual = hash('sha256', json_encode($detalhe, JSON_UNESCAPED_UNICODE) . '|' . ($hashAnterior ?? '') . '|');

            DB::table('JORNADA_LEDGER')->updateOrInsert(
                [
                    'FUNCIONARIO_ID' => (int) $adminFuncionarioId,
                    'COMPETENCIA' => $competencia,
                    'JORNADA_DATA' => $dia->toDateString(),
                ],
                [
                    'LANCAMENTO_TIPO' => $delta >= 0 ? 'credito' : 'debito',
                    'MINUTOS_TRABALHADOS' => (int) $trabalhados,
                    'MINUTOS_META' => (int) $meta,
                    'MINUTOS_DELTA' => (int) $delta,
                    'HORAS_CREDITADAS' => $cred,
                    'HORAS_DEBITADAS' => $deb,
                    'SALDO_HORAS' => round($delta / 60, 2),
                    'VERSAO' => 1,
                    'ORIGEM' => 'seed_sidebar',
                    'MOTIVO' => 'seed_admin_painel_profissional',
                    'DETALHE' => json_encode($detalhe, JSON_UNESCAPED_UNICODE),
                    'HASH_AUDITORIA' => $hashAtual,
                    ...($temHashAnterior ? ['HASH_ANTERIOR' => $hashAnterior] : []),
                    'GERADO_POR_USUARIO_ID' => (int) $adminId,
                    'GERADO_EM' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $hashAnterior = $hashAtual;

            // Espelha no REGISTRO_PONTO para alimentar calendário e resumo diário do banco.
            if (Schema::hasTable('REGISTRO_PONTO')) {
                $colsPonto = Schema::getColumnListing('REGISTRO_PONTO');
                $tipos = ['entrada' => '08:00:00', 'saida_alm' => '12:00:00', 'ret_alm' => '13:00:00'];
                $saidaFinal = (new Carbon($dia->toDateString() . ' 17:00:00'))
                    ->addMinutes(max(0, $delta))
                    ->format('H:i:s');
                $tipos['saida'] = $saidaFinal;

                foreach ($tipos as $tipo => $hora) {
                    $dataHora = $dia->toDateString() . ' ' . $hora;
                    $existe = DB::table('REGISTRO_PONTO')
                        ->where('FUNCIONARIO_ID', (int) $adminFuncionarioId)
                        ->where('REGISTRO_DATA_HORA', $dataHora)
                        ->where('REGISTRO_TIPO', $tipo)
                        ->exists();
                    if ($existe) {
                        continue;
                    }

                    $payload = [
                        'FUNCIONARIO_ID' => (int) $adminFuncionarioId,
                        'REGISTRO_DATA_HORA' => $dataHora,
                        'REGISTRO_TIPO' => $tipo,
                    ];
                    if (in_array('REGISTRO_ORIGEM', $colsPonto, true)) {
                        $payload['REGISTRO_ORIGEM'] = 'SEED';
                    }
                    DB::table('REGISTRO_PONTO')->insert($payload);
                }
            }
        }

        if (Schema::hasTable('BANCO_HORAS')) {
            $credMes = (float) DB::table('JORNADA_LEDGER')
                ->where('FUNCIONARIO_ID', (int) $adminFuncionarioId)
                ->where('COMPETENCIA', $competencia)
                ->sum('HORAS_CREDITADAS');
            $debMes = (float) DB::table('JORNADA_LEDGER')
                ->where('FUNCIONARIO_ID', (int) $adminFuncionarioId)
                ->where('COMPETENCIA', $competencia)
                ->sum('HORAS_DEBITADAS');

            DB::table('BANCO_HORAS')->updateOrInsert(
                ['FUNCIONARIO_ID' => (int) $adminFuncionarioId, 'COMPETENCIA' => $competencia],
                [
                    'HORAS_CREDITADAS' => round($credMes, 2),
                    'HORAS_DEBITADAS' => round($debMes, 2),
                    'TIPO' => 'APURACAO',
                    'OBSERVACAO' => 'Consolidação seed admin profissional',
                    'REGISTRADO_POR' => (int) $adminId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedHoraExtraEPlantao($funcionarios, $adminId): void
    {
        if (Schema::hasTable('HORA_EXTRA')) {
            $colsHe = Schema::getColumnListing('HORA_EXTRA');
            $competenciaAtual = now()->format('Y-m');

            foreach ($funcionarios->take(8) as $f) {
                $lot = null;
                if (Schema::hasTable('LOTACAO')) {
                    $lot = DB::table('LOTACAO as l')
                        ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                        ->where('l.FUNCIONARIO_ID', $f->FUNCIONARIO_ID)
                        ->whereNull('l.LOTACAO_DATA_FIM')
                        ->select('l.SETOR_ID', 's.UNIDADE_ID')
                        ->first();
                }

                $check = DB::table('HORA_EXTRA')->where('FUNCIONARIO_ID', $f->FUNCIONARIO_ID);
                if (in_array('COMPETENCIA', $colsHe, true)) {
                    $check->where('COMPETENCIA', $competenciaAtual);
                }
                if ($check->exists()) {
                    continue;
                }

                $row = [];
                if (in_array('FUNCIONARIO_ID', $colsHe, true))
                    $row['FUNCIONARIO_ID'] = $f->FUNCIONARIO_ID;
                if (in_array('UNIDADE_ID', $colsHe, true))
                    $row['UNIDADE_ID'] = $lot?->UNIDADE_ID;
                if (in_array('SETOR_ID', $colsHe, true))
                    $row['SETOR_ID'] = $lot?->SETOR_ID;
                if (in_array('COMPETENCIA', $colsHe, true))
                    $row['COMPETENCIA'] = $competenciaAtual;
                if (in_array('DATA_REALIZACAO', $colsHe, true))
                    $row['DATA_REALIZACAO'] = now()->subDay()->toDateString();
                if (in_array('HORA_INICIO', $colsHe, true))
                    $row['HORA_INICIO'] = '18:00';
                if (in_array('HORA_FIM', $colsHe, true))
                    $row['HORA_FIM'] = '20:00';
                if (in_array('TOTAL_HORAS', $colsHe, true))
                    $row['TOTAL_HORAS'] = 2.0;
                if (in_array('TIPO_HORA_EXTRA', $colsHe, true))
                    $row['TIPO_HORA_EXTRA'] = '50_PORCENTO';
                if (in_array('PERCENTUAL', $colsHe, true))
                    $row['PERCENTUAL'] = 50;
                if (in_array('VALOR_HORA_BASE', $colsHe, true))
                    $row['VALOR_HORA_BASE'] = 25.00;
                if (in_array('VALOR_CALCULADO', $colsHe, true))
                    $row['VALOR_CALCULADO'] = 75.00;
                if (in_array('AUTORIZADO_POR', $colsHe, true))
                    $row['AUTORIZADO_POR'] = $adminId;
                if (in_array('STATUS', $colsHe, true))
                    $row['STATUS'] = 'PENDENTE';
                if (in_array('OBSERVACAO', $colsHe, true))
                    $row['OBSERVACAO'] = 'Seed cobertura sidebar - hora extra';
                if (in_array('created_at', $colsHe, true))
                    $row['created_at'] = now();
                if (in_array('updated_at', $colsHe, true))
                    $row['updated_at'] = now();

                DB::table('HORA_EXTRA')->insert($row);
            }
        }

        if (Schema::hasTable('PLANTAO_EXTRA')) {
            $colsPe = Schema::getColumnListing('PLANTAO_EXTRA');

            foreach ($funcionarios->take(6) as $f) {
                $dataBase = now()->subDays(2)->toDateString();
                $check = DB::table('PLANTAO_EXTRA')->where('FUNCIONARIO_ID', $f->FUNCIONARIO_ID);
                if (in_array('PLANTAO_DATA', $colsPe, true))
                    $check->where('PLANTAO_DATA', $dataBase);
                elseif (in_array('DATA_PLANTAO', $colsPe, true))
                    $check->where('DATA_PLANTAO', $dataBase);

                if ($check->exists()) {
                    continue;
                }

                $row = [];
                if (in_array('FUNCIONARIO_ID', $colsPe, true))
                    $row['FUNCIONARIO_ID'] = $f->FUNCIONARIO_ID;
                if (in_array('SETOR_ID', $colsPe, true))
                    $row['SETOR_ID'] = DB::table('SETOR')->value('SETOR_ID');
                if (in_array('ESCALA_ID', $colsPe, true))
                    $row['ESCALA_ID'] = DB::table('ESCALA')->value('ESCALA_ID');
                if (in_array('PLANTAO_DATA', $colsPe, true))
                    $row['PLANTAO_DATA'] = $dataBase;
                if (in_array('DATA_PLANTAO', $colsPe, true))
                    $row['DATA_PLANTAO'] = $dataBase;
                if (in_array('PLANTAO_TURNO', $colsPe, true))
                    $row['PLANTAO_TURNO'] = 'N';
                if (in_array('PLANTAO_HORAS', $colsPe, true))
                    $row['PLANTAO_HORAS'] = 12;
                if (in_array('TOTAL_HORAS', $colsPe, true))
                    $row['TOTAL_HORAS'] = 12;
                if (in_array('PLANTAO_STATUS', $colsPe, true))
                    $row['PLANTAO_STATUS'] = 'PENDENTE';
                if (in_array('STATUS', $colsPe, true))
                    $row['STATUS'] = 'PENDENTE';
                if (in_array('PLANTAO_MOTIVO', $colsPe, true))
                    $row['PLANTAO_MOTIVO'] = 'Seed cobertura sidebar - plantão';
                if (in_array('created_at', $colsPe, true))
                    $row['created_at'] = now();
                if (in_array('updated_at', $colsPe, true))
                    $row['updated_at'] = now();

                DB::table('PLANTAO_EXTRA')->insert($row);
            }
        }
    }

    private function seedEscalaESubstituicoes($funcionarios): void
    {
        if (!(Schema::hasTable('ESCALA') && Schema::hasTable('DETALHE_ESCALA'))) {
            return;
        }

        $colsEscala = Schema::getColumnListing('ESCALA');
        $setores = Schema::hasTable('SETOR')
            ? DB::table('SETOR')->orderBy('SETOR_ID')->limit(4)->pluck('SETOR_ID')->values()->all()
            : [];
        if (empty($setores)) {
            $setores = [1];
        }

        $competencias = [
            now()->format('Y-m'),
            now()->copy()->subMonth()->format('Y-m'),
            now()->copy()->addMonth()->format('Y-m'),
        ];

        $escalasSeedadas = [];
        foreach ($competencias as $comp) {
            foreach (array_slice($setores, 0, 3) as $setorId) {
                $escala = DB::table('ESCALA')
                    ->where('ESCALA_COMPETENCIA', $comp)
                    ->where('SETOR_ID', $setorId)
                    ->first();

                if (!$escala) {
                    $dadosEscala = [
                        'ESCALA_COMPETENCIA' => $comp,
                        'SETOR_ID' => $setorId,
                    ];
                    if (in_array('ESCALA_STATUS', $colsEscala, true)) {
                        $dadosEscala['ESCALA_STATUS'] = EscalaWorkflowStatus::RASCUNHO;
                    }
                    if (in_array('ESCALA_ATIVO', $colsEscala, true))
                        $dadosEscala['ESCALA_ATIVO'] = 1;
                    if (in_array('ESCALA_OBSERVACAO', $colsEscala, true))
                        $dadosEscala['ESCALA_OBSERVACAO'] = 'Escala seedada para cobertura de sidebar';
                    if (in_array('ESCALA_DESCRICAO', $colsEscala, true))
                        $dadosEscala['ESCALA_DESCRICAO'] = "Escala {$comp}";
                    if (in_array('created_at', $colsEscala, true))
                        $dadosEscala['created_at'] = now();
                    if (in_array('updated_at', $colsEscala, true))
                        $dadosEscala['updated_at'] = now();

                    $escalaId = DB::table('ESCALA')->insertGetId($dadosEscala);
                } else {
                    $escalaId = $escala->ESCALA_ID;
                }

                $escalasSeedadas[] = ['id' => $escalaId, 'competencia' => $comp, 'setor_id' => $setorId];
            }
        }

        $poolFuncionarios = $funcionarios->take(18)->values();
        if ($poolFuncionarios->isEmpty()) {
            return;
        }

        $turnoMap = Schema::hasTable('TURNO')
            ? DB::table('TURNO')
                ->whereIn('TURNO_SIGLA', ['M', 'V', 'N', 'I', 'F', 'SO', 'AT'])
                ->pluck('TURNO_ID', 'TURNO_SIGLA')
            : collect();
        $cicloTurno = ['M', 'V', 'N', 'I', 'F'];
        $mapaDetalhesEscala = [];

        foreach ($escalasSeedadas as $idxEscala => $esc) {
            $baseMes = Carbon::createFromFormat('Y-m', $esc['competencia'])->startOfMonth();
            $funcsEscala = $poolFuncionarios
                ->slice(($idxEscala * 4) % max(1, $poolFuncionarios->count()), 8)
                ->values();
            if ($funcsEscala->isEmpty()) {
                $funcsEscala = $poolFuncionarios->take(8)->values();
            }

            foreach ($funcsEscala as $idxFunc => $f) {
                DB::table('DETALHE_ESCALA')->updateOrInsert(
                    ['ESCALA_ID' => $esc['id'], 'FUNCIONARIO_ID' => $f->FUNCIONARIO_ID],
                    Schema::hasColumn('DETALHE_ESCALA', 'updated_at') ? ['updated_at' => now()] : []
                );

                $detalheId = DB::table('DETALHE_ESCALA')
                    ->where('ESCALA_ID', $esc['id'])
                    ->where('FUNCIONARIO_ID', $f->FUNCIONARIO_ID)
                    ->value('DETALHE_ESCALA_ID');

                if (!$detalheId || !Schema::hasTable('DETALHE_ESCALA_ITEM') || $turnoMap->isEmpty()) {
                    continue;
                }

                $mapaDetalhesEscala[$esc['id']][] = [
                    'detalhe_id' => $detalheId,
                    'funcionario_id' => $f->FUNCIONARIO_ID,
                    'base' => $baseMes,
                    'idx' => $idxFunc,
                ];

                for ($d = 0; $d < 16; $d++) {
                    $dia = $baseMes->copy()->addDays($d);
                    if ($dia->isWeekend()) {
                        continue;
                    }
                    $sigla = $cicloTurno[($idxFunc + $d) % count($cicloTurno)];
                    $turnoId = $turnoMap[$sigla] ?? null;
                    if (!$turnoId) {
                        continue;
                    }

                    DB::table('DETALHE_ESCALA_ITEM')->updateOrInsert(
                        [
                            'DETALHE_ESCALA_ID' => $detalheId,
                            'DETALHE_ESCALA_ITEM_DATA' => $dia->toDateString(),
                        ],
                        ['TURNO_ID' => $turnoId]
                    );
                }
            }
        }

        if (Schema::hasTable('SUBSTITUICAO_ESCALA')) {
            $colsSubEscala = Schema::getColumnListing('SUBSTITUICAO_ESCALA');
            foreach ($escalasSeedadas as $esc) {
                $detalhes = $mapaDetalhesEscala[$esc['id']] ?? [];
                if (count($detalhes) < 2) {
                    continue;
                }

                $solicitante = $detalhes[0];
                $substituto = $detalhes[1];
                $dataSub = Carbon::createFromFormat('Y-m', $esc['competencia'])
                    ->startOfMonth()
                    ->addWeekday()
                    ->toDateString();

                $existe = DB::table('SUBSTITUICAO_ESCALA')
                    ->where('ESCALA_ID', $esc['id'])
                    ->where('FUNCIONARIO_ID', $solicitante['funcionario_id'])
                    ->where('SUBSTITUICAO_ESCALA_DATA', $dataSub)
                    ->exists();
                if ($existe) {
                    continue;
                }

                $payload = [
                    'ESCALA_ID' => $esc['id'],
                    'FUNCIONARIO_ID' => $solicitante['funcionario_id'],
                    'FUNCIONARIO_SUBSTITUTO_ID' => $substituto['funcionario_id'],
                    'SUBSTITUICAO_ESCALA_DATA' => $dataSub,
                ];
                if (in_array('SUBSTITUICAO_ESCALA_JUSTIFICATIVA', $colsSubEscala, true))
                    $payload['SUBSTITUICAO_ESCALA_JUSTIFICATIVA'] = 'Seed de cobertura';
                if (in_array('SUBSTITUICAO_ESCALA_STATUS', $colsSubEscala, true))
                    $payload['SUBSTITUICAO_ESCALA_STATUS'] = 'pendente';

                DB::table('SUBSTITUICAO_ESCALA')->insert($payload);
            }
        }

        if (Schema::hasTable('SUBSTITUICAO')) {
            $f1 = $funcionarios->first()?->FUNCIONARIO_ID;
            $f2 = $funcionarios->skip(1)->first()?->FUNCIONARIO_ID;
            $escalaId = $escalasSeedadas[0]['id'] ?? null;
            if ($f1 && $f2 && $escalaId) {
                $jaTem = DB::table('SUBSTITUICAO')
                    ->where('ESCALA_ID', $escalaId)
                    ->where('SOLICITANTE_FUNCIONARIO_ID', $f1)
                    ->where('SUBSTITUICAO_DATA', now()->toDateString())
                    ->exists();
                if (!$jaTem) {
                    DB::table('SUBSTITUICAO')->insert([
                        'SOLICITANTE_FUNCIONARIO_ID' => $f1,
                        'SUBSTITUTO_FUNCIONARIO_ID' => $f2,
                        'ESCALA_ID' => $escalaId,
                        'SUBSTITUICAO_DATA' => now()->toDateString(),
                        'SUBSTITUICAO_TURNO' => 'M',
                        'SUBSTITUICAO_STATUS' => 'aprovada',
                        'SUBSTITUICAO_MOTIVO' => 'Seed de cobertura',
                        'SUBSTITUICAO_DT_SOLICITACAO' => now()->toDateString(),
                    ]);
                }
            }
        }
    }

    private function seedTurnosEscala(): void
    {
        if (!Schema::hasTable('TURNO')) {
            return;
        }

        $colunas = Schema::getColumnListing('TURNO');
        $defs = [
            ['sigla' => 'M', 'nome' => 'Matutino', 'inicio' => '08:00', 'fim' => '12:00', 'carga' => 4],
            ['sigla' => 'V', 'nome' => 'Vespertino', 'inicio' => '13:00', 'fim' => '17:00', 'carga' => 4],
            ['sigla' => 'N', 'nome' => 'Noturno', 'inicio' => '19:00', 'fim' => '07:00', 'carga' => 12],
            ['sigla' => 'I', 'nome' => 'Integral', 'inicio' => '08:00', 'fim' => '17:00', 'carga' => 8],
            ['sigla' => 'F', 'nome' => 'Folga', 'inicio' => null, 'fim' => null, 'carga' => 0],
            ['sigla' => 'SO', 'nome' => 'Sobreaviso', 'inicio' => null, 'fim' => null, 'carga' => 0],
            ['sigla' => 'AT', 'nome' => 'Atestado', 'inicio' => null, 'fim' => null, 'carga' => 0],
        ];

        foreach ($defs as $def) {
            $payload = [];
            if (in_array('TURNO_NOME', $colunas, true)) {
                $payload['TURNO_NOME'] = $def['nome'];
            }
            if (in_array('TURNO_SIGLA', $colunas, true)) {
                $payload['TURNO_SIGLA'] = $def['sigla'];
            }
            if (in_array('TURNO_DESCRICAO', $colunas, true)) {
                $payload['TURNO_DESCRICAO'] = $def['nome'];
            }
            if (in_array('TURNO_HORA_INICIO', $colunas, true)) {
                $payload['TURNO_HORA_INICIO'] = $def['inicio'];
            }
            if (in_array('TURNO_HORA_FIM', $colunas, true)) {
                $payload['TURNO_HORA_FIM'] = $def['fim'];
            }
            if (in_array('TURNO_HORA_ENTRADA', $colunas, true)) {
                $payload['TURNO_HORA_ENTRADA'] = $def['inicio'];
            }
            if (in_array('TURNO_HORA_SAIDA', $colunas, true)) {
                $payload['TURNO_HORA_SAIDA'] = $def['fim'];
            }
            if (in_array('TURNO_CARGA_HORARIA', $colunas, true)) {
                $payload['TURNO_CARGA_HORARIA'] = $def['carga'];
            }
            if (in_array('TURNO_ATIVO', $colunas, true)) {
                $payload['TURNO_ATIVO'] = 1;
            }

            DB::table('TURNO')->updateOrInsert(
                ['TURNO_SIGLA' => $def['sigla']],
                $payload
            );
        }
    }

    private function seedDeclaracoes($funcionarios): void
    {
        if (!Schema::hasTable('DECLARACAO')) {
            return;
        }

        foreach ($funcionarios->take(8) as $f) {
            $existe = DB::table('DECLARACAO')
                ->where('FUNCIONARIO_ID', $f->FUNCIONARIO_ID)
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('DECLARACAO')->insert([
                'FUNCIONARIO_ID' => $f->FUNCIONARIO_ID,
                'DECLARACAO_TIPO' => 'Declaração de Vínculo',
                'DECLARACAO_STATUS' => 'pronto',
                'DECLARACAO_OBS' => 'Documento seedado para testes',
                'DECLARACAO_DT_SOLICITACAO' => now()->subDays(2)->toDateString(),
                'DECLARACAO_DT_CONCLUSAO' => now()->toDateString(),
            ]);
        }
    }

    private function seedFolha2026($funcionarios): void
    {
        if (!(Schema::hasTable('FOLHA') && Schema::hasTable('DETALHE_FOLHA'))) {
            return;
        }

        $setor = Schema::hasTable('SETOR') ? DB::table('SETOR')->value('SETOR_ID') : null;
        $folhaCols = Schema::getColumnListing('FOLHA');
        $detCols = Schema::getColumnListing('DETALHE_FOLHA');

        foreach (['202601', '202602', '202603', '202604'] as $competencia) {
            $folha = DB::table('FOLHA')->where('FOLHA_COMPETENCIA', $competencia)->first();
            if (!$folha) {
                $dados = ['FOLHA_COMPETENCIA' => $competencia];
                if (in_array('SETOR_ID', $folhaCols, true))
                    $dados['SETOR_ID'] = $setor;
                if (in_array('FOLHA_STATUS', $folhaCols, true))
                    $dados['FOLHA_STATUS'] = is_numeric(DB::table('FOLHA')->value('FOLHA_STATUS')) ? 4 : 'Fechada';
                if (in_array('FOLHA_ATIVO', $folhaCols, true))
                    $dados['FOLHA_ATIVO'] = 1;
                if (in_array('FOLHA_QTD_SERVIDORES', $folhaCols, true))
                    $dados['FOLHA_QTD_SERVIDORES'] = $funcionarios->count();
                if (in_array('FOLHA_VALOR_TOTAL', $folhaCols, true))
                    $dados['FOLHA_VALOR_TOTAL'] = 0;
                $folhaId = DB::table('FOLHA')->insertGetId($dados);
            } else {
                $folhaId = $folha->FOLHA_ID;
            }

            foreach ($funcionarios->take(15) as $f) {
                $dadosDet = [];
                if (in_array('DETALHE_FOLHA_PROVENTOS', $detCols, true))
                    $dadosDet['DETALHE_FOLHA_PROVENTOS'] = 3200;
                if (in_array('DETALHE_FOLHA_DESCONTOS', $detCols, true))
                    $dadosDet['DETALHE_FOLHA_DESCONTOS'] = 420;
                if (in_array('DETALHE_FOLHA_LIQUIDO', $detCols, true))
                    $dadosDet['DETALHE_FOLHA_LIQUIDO'] = 2780;
                DB::table('DETALHE_FOLHA')->updateOrInsert(
                    ['FOLHA_ID' => $folhaId, 'FUNCIONARIO_ID' => $f->FUNCIONARIO_ID],
                    $dadosDet
                );
            }
        }
    }

    private function seedOrcamento(): void
    {
        if (!Schema::hasTable('ORCAMENTO_PPA')) {
            return;
        }

        $ppaCols = Schema::getColumnListing('ORCAMENTO_PPA');
        $ppaId = DB::table('ORCAMENTO_PPA')->value('PPA_ID');
        if (!$ppaId) {
            $row = [
                'PPA_DESCRICAO' => 'PPA 2024-2027 - Prefeitura Municipal',
                'PPA_ANO_INICIO' => 2024,
                'PPA_ANO_FIM' => 2027,
            ];
            if (in_array('PPA_STATUS', $ppaCols, true))
                $row['PPA_STATUS'] = 'ATIVO';
            if (in_array('PPA_ATIVO', $ppaCols, true))
                $row['PPA_ATIVO'] = 1;
            if (in_array('created_at', $ppaCols, true))
                $row['created_at'] = now();
            if (in_array('updated_at', $ppaCols, true))
                $row['updated_at'] = now();
            $ppaId = DB::table('ORCAMENTO_PPA')->insertGetId($row);
        }

        $programaCols = Schema::hasTable('ORCAMENTO_PROGRAMA') ? Schema::getColumnListing('ORCAMENTO_PROGRAMA') : [];
        if (Schema::hasTable('ORCAMENTO_PROGRAMA') && DB::table('ORCAMENTO_PROGRAMA')->count() === 0) {
            $linhas = [
                ['PPA_ID' => $ppaId, 'PROGRAMA_CODIGO' => '0001', 'PROGRAMA_NOME' => 'Gestão Administrativa'],
                ['PPA_ID' => $ppaId, 'PROGRAMA_CODIGO' => '0010', 'PROGRAMA_NOME' => 'Saúde Preventiva'],
            ];
            foreach ($linhas as &$l) {
                if (in_array('PROGRAMA_OBJETIVO', $programaCols, true))
                    $l['PROGRAMA_OBJETIVO'] = 'Seed inicial ERP';
                if (in_array('PROGRAMA_VALOR_TOTAL', $programaCols, true))
                    $l['PROGRAMA_VALOR_TOTAL'] = 4000000;
                if (in_array('created_at', $programaCols, true))
                    $l['created_at'] = now();
                if (in_array('updated_at', $programaCols, true))
                    $l['updated_at'] = now();
            }
            DB::table('ORCAMENTO_PROGRAMA')->insert($linhas);
        }

        $acaoCols = Schema::hasTable('ORCAMENTO_ACAO') ? Schema::getColumnListing('ORCAMENTO_ACAO') : [];
        if (Schema::hasTable('ORCAMENTO_ACAO') && DB::table('ORCAMENTO_ACAO')->count() === 0) {
            $prog = DB::table('ORCAMENTO_PROGRAMA')->orderBy('PROGRAMA_ID')->pluck('PROGRAMA_ID')->values();
            if ($prog->count() > 0) {
                $linhas = [
                    ['PROGRAMA_ID' => $prog[0], 'ACAO_CODIGO' => '2001', 'ACAO_NOME' => 'Manutenção da Secretaria', 'ACAO_TIPO' => 'ATIVIDADE', 'ACAO_VALOR_PREVISTO' => 500000],
                    ['PROGRAMA_ID' => $prog[0], 'ACAO_CODIGO' => '2002', 'ACAO_NOME' => 'Pagamento de Pessoal', 'ACAO_TIPO' => 'ATIVIDADE', 'ACAO_VALOR_PREVISTO' => 3500000],
                ];
                foreach ($linhas as &$l) {
                    if (in_array('created_at', $acaoCols, true))
                        $l['created_at'] = now();
                    if (in_array('updated_at', $acaoCols, true))
                        $l['updated_at'] = now();
                }
                DB::table('ORCAMENTO_ACAO')->insert($linhas);
            }
        }

        $loaCols = Schema::hasTable('ORCAMENTO_LOA') ? Schema::getColumnListing('ORCAMENTO_LOA') : [];
        if (Schema::hasTable('ORCAMENTO_LOA') && DB::table('ORCAMENTO_LOA')->count() === 0) {
            $acoes = DB::table('ORCAMENTO_ACAO')->orderBy('ACAO_ID')->pluck('ACAO_ID')->take(2)->values();
            foreach ($acoes as $acaoId) {
                $row = [
                    'ACAO_ID' => $acaoId,
                    'LOA_ANO' => 2026,
                    'LOA_VALOR_APROVADO' => 500000,
                    'LOA_VALOR_ADICIONADO' => 0,
                    'LOA_VALOR_REDUZIDO' => 0,
                ];
                if (in_array('LOA_NATUREZA_DESPESA', $loaCols, true))
                    $row['LOA_NATUREZA_DESPESA'] = '3.1.90.11.00';
                if (in_array('LOA_FONTE_RECURSO', $loaCols, true))
                    $row['LOA_FONTE_RECURSO'] = 'TESOURO MUNICIPAL';
                if (in_array('created_at', $loaCols, true))
                    $row['created_at'] = now();
                if (in_array('updated_at', $loaCols, true))
                    $row['updated_at'] = now();
                DB::table('ORCAMENTO_LOA')->insert($row);
            }
        }
    }

    private function seedTesouraria(): void
    {
        if (!Schema::hasTable('CONTA_BANCARIA')) {
            return;
        }

        $contaCols = Schema::getColumnListing('CONTA_BANCARIA');
        if (DB::table('CONTA_BANCARIA')->count() === 0) {
            $contasBase = [
                ['descricao' => 'Conta Corrente Principal', 'numero' => '12345-6'],
                ['descricao' => 'Conta Folha de Pagamento', 'numero' => '12345-7'],
            ];
            foreach ($contasBase as $base) {
                $row = [];
                if (in_array('CONTA_DESCRICAO', $contaCols, true))
                    $row['CONTA_DESCRICAO'] = $base['descricao'];
                if (in_array('CONTA_BANCO', $contaCols, true))
                    $row['CONTA_BANCO'] = in_array('CONTA_BANCO_NOME', $contaCols, true) ? '001' : 'Banco do Brasil';
                if (in_array('CONTA_BANCO_NOME', $contaCols, true))
                    $row['CONTA_BANCO_NOME'] = 'Banco do Brasil';
                if (in_array('CONTA_AGENCIA', $contaCols, true))
                    $row['CONTA_AGENCIA'] = '0001';
                if (in_array('CONTA_NUMERO', $contaCols, true))
                    $row['CONTA_NUMERO'] = $base['numero'];
                if (in_array('CONTA_TIPO', $contaCols, true))
                    $row['CONTA_TIPO'] = 'CORRENTE';
                if (in_array('CONTA_SALDO_INICIAL', $contaCols, true))
                    $row['CONTA_SALDO_INICIAL'] = 0;
                if (in_array('CONTA_SALDO_DATA', $contaCols, true))
                    $row['CONTA_SALDO_DATA'] = now()->toDateString();
                if (in_array('CONTA_ATIVA', $contaCols, true))
                    $row['CONTA_ATIVA'] = 1;
                if (in_array('created_at', $contaCols, true))
                    $row['created_at'] = now();
                if (in_array('updated_at', $contaCols, true))
                    $row['updated_at'] = now();
                DB::table('CONTA_BANCARIA')->insert($row);
            }
        }

        $movCols = Schema::hasTable('MOVIMENTACAO_BANCARIA') ? Schema::getColumnListing('MOVIMENTACAO_BANCARIA') : [];
        if (Schema::hasTable('MOVIMENTACAO_BANCARIA') && DB::table('MOVIMENTACAO_BANCARIA')->count() === 0) {
            $conta = DB::table('CONTA_BANCARIA')->value('CONTA_ID');
            if ($conta) {
                $movBase = [
                    ['tipo' => 'CREDITO', 'valor' => 850000, 'hist' => 'Transferência FPM', 'origem' => 'RECEITA', 'status' => 'CONCILIADO', 'data' => now()->subDays(1)->toDateString()],
                    ['tipo' => 'DEBITO', 'valor' => 120000, 'hist' => 'Folha de pagamento', 'origem' => 'FOLHA', 'status' => 'PENDENTE', 'data' => now()->toDateString()],
                ];
                foreach ($movBase as $base) {
                    $row = [
                        'CONTA_ID' => $conta,
                        'MOV_DATA' => $base['data'],
                        'MOV_TIPO' => $base['tipo'],
                        'MOV_VALOR' => $base['valor'],
                    ];
                    if (in_array('MOV_HISTORICO', $movCols, true))
                        $row['MOV_HISTORICO'] = $base['hist'];
                    if (in_array('MOV_ORIGEM', $movCols, true))
                        $row['MOV_ORIGEM'] = $base['origem'];
                    if (in_array('MOV_ORIGEM_TIPO', $movCols, true))
                        $row['MOV_ORIGEM_TIPO'] = $base['origem'];
                    if (in_array('MOV_STATUS', $movCols, true))
                        $row['MOV_STATUS'] = $base['status'];
                    if (in_array('created_at', $movCols, true))
                        $row['created_at'] = now();
                    if (in_array('updated_at', $movCols, true))
                        $row['updated_at'] = now();
                    DB::table('MOVIMENTACAO_BANCARIA')->insert($row);
                }
            }
        }
    }

    private function seedReceita(): void
    {
        if (!Schema::hasTable('RECEITA_LANCAMENTO') || DB::table('RECEITA_LANCAMENTO')->count() > 0) {
            return;
        }

        DB::table('RECEITA_LANCAMENTO')->insert([
            [
                'RECEITA_DATA' => now()->subMonths(2)->toDateString(),
                'RECEITA_ANO' => now()->subMonths(2)->year,
                'RECEITA_MES' => now()->subMonths(2)->month,
                'RECEITA_CODIGO_NATUREZA' => '1.1.1.0.0',
                'RECEITA_DESCRICAO' => 'IPTU - Imposto Predial',
                'RECEITA_TIPO' => 'TRIBUTARIA',
                'RECEITA_VALOR_PREVISTO' => 240000,
                'RECEITA_VALOR_ARRECADADO' => 198000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'RECEITA_DATA' => now()->subMonths(1)->toDateString(),
                'RECEITA_ANO' => now()->subMonths(1)->year,
                'RECEITA_MES' => now()->subMonths(1)->month,
                'RECEITA_CODIGO_NATUREZA' => '6.1.1.0.0',
                'RECEITA_DESCRICAO' => 'FPM - Fundo Participação Municípios',
                'RECEITA_TIPO' => 'TRANSFERENCIAS_CORRENTES',
                'RECEITA_VALOR_PREVISTO' => 850000,
                'RECEITA_VALOR_ARRECADADO' => 850000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
