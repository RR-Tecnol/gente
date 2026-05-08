<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemPhase2CoverageSeeder extends Seeder
{
    public function run(): void
    {
        $userIds = $this->listarUsuariosAtivos();
        $adminId = (int) ($userIds['admin'] ?? 1);

        $this->seedVinculoAdminAnaCristina($adminId);
        $this->seedCargosHospitalares($adminId);
        $this->seedDistribuicaoHospitalarFuncionarios();
        $this->seedContratosEVinculosConsistentes();
        $this->seedMultilotacaoHospitalar();
        $this->seedPerfisAcessoPorResponsabilidade();
        $this->seedConectividadeCrossModule($adminId);
        $this->seedRhWorkflowAvancado($adminId);
        $this->seedDesenvolvimentoSaudeOcupacional($adminId);
        $this->seedMedicinaOcupacionalDetalhada($adminId);
        $this->seedSegurancaTrabalhoDetalhada($adminId);
        $this->seedTreinamentosDetalhados($adminId);
        $this->seedAvaliacoesDesempenhoDetalhadas($adminId);
        $this->seedAbonoFaltasHistorico($adminId);
        $this->seedPatrimonioEFrotas($adminId);
        $this->seedComplianceEControleExterno($adminId);
        $this->seedComunicacaoERotinaPorPerfil($adminId);
        $this->seedFinanceiroFolhaConectado($adminId);
        $this->seedNotificacoesOperacionais($userIds['todos'], $adminId);
        $this->seedComunicados($adminId);
        $this->seedOuvidoria($adminId);
        $this->seedPesquisasSatisfacao($adminId);
        $this->seedComprasContratos($adminId);
        $this->seedAlmoxarifado($adminId);
        $this->seedSobreavisoEAcionamentos($adminId);
        $this->seedModulosIntegrados($adminId);
    }

    private function seedVinculoAdminAnaCristina(int $adminId): void
    {
        if (
            !$adminId ||
            !Schema::hasTable('FUNCIONARIO') ||
            !Schema::hasTable('PESSOA') ||
            !Schema::hasColumn('FUNCIONARIO', 'USUARIO_ID')
        ) {
            return;
        }

        $funcAna = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->where('p.PESSOA_CPF_NUMERO', '47026653038')
            ->value('f.FUNCIONARIO_ID');
        if (!$funcAna) {
            return;
        }

        DB::table('FUNCIONARIO')
            ->where('FUNCIONARIO_ID', $funcAna)
            ->update(['USUARIO_ID' => $adminId]);
    }

    private function listarUsuariosAtivos(): array
    {
        if (!Schema::hasTable('USUARIO')) {
            return ['todos' => collect([1]), 'admin' => 1];
        }

        $todos = DB::table('USUARIO')
            ->where('USUARIO_ATIVO', 1)
            ->pluck('USUARIO_ID');

        $admin = DB::table('USUARIO_PERFIL')
            ->where('USUARIO_PERFIL_ATIVO', 1)
            ->where('PERFIL_ID', 1)
            ->value('USUARIO_ID');

        return [
            'todos' => $todos->isNotEmpty() ? $todos : collect([1]),
            'admin' => $admin ?: ($todos->first() ?: 1),
        ];
    }

    private function seedNotificacoesOperacionais($usuarios, int $adminId): void
    {
        if (!Schema::hasTable('NOTIFICACAO')) {
            return;
        }

        foreach ($usuarios->take(8) as $uid) {
            $existe = DB::table('NOTIFICACAO')
                ->where('USUARIO_ID', (int) $uid)
                ->where('NOTIFICACAO_TIPO', 'bh_operacional')
                ->where('NOTIFICACAO_TITULO', 'like', 'Governança operacional%')
                ->exists();

            if ($existe) {
                continue;
            }

            DB::table('NOTIFICACAO')->insert([
                'USUARIO_ID' => (int) $uid,
                'NOTIFICACAO_TITULO' => 'Governança operacional — Cobertura de escala',
                'NOTIFICACAO_BODY' => 'Simulação seed: revisar cobertura e riscos de déficit na competência atual.',
                'NOTIFICACAO_TIPO' => 'bh_operacional',
                'NOTIFICACAO_ICONE' => '🧭',
                'NOTIFICACAO_URL' => '/banco-horas?modo=equipe',
                'NOTIFICACAO_LIDA' => (int) ((int) $uid % 2 === 0),
                'NOTIFICACAO_DT_CRIACAO' => now()->subDays((int) $uid % 5),
            ]);
        }

        DB::table('NOTIFICACAO')->insert([
            'USUARIO_ID' => $adminId,
            'NOTIFICACAO_TITULO' => 'Painel executivo — alertas consolidados',
            'NOTIFICACAO_BODY' => 'Seed fase 2: consolidado de alertas operacionais por setor.',
            'NOTIFICACAO_TIPO' => 'info',
            'NOTIFICACAO_ICONE' => '📊',
            'NOTIFICACAO_URL' => '/painel-secretario',
            'NOTIFICACAO_LIDA' => 0,
            'NOTIFICACAO_DT_CRIACAO' => now()->subHours(3),
        ]);
    }

    private function seedCargosHospitalares(int $adminId): void
    {
        if (!Schema::hasTable('CARGO')) {
            return;
        }

        $cols = Schema::getColumnListing('CARGO');
        $cargos = [
            ['nome' => 'Médico Plantonista UTI', 'sigla' => 'MED UTI', 'cbo' => '225125', 'escolaridade' => 8, 'gestao' => 0, 'salario' => 18500, 'desconto_hora' => 110, 'inicio' => '2024-01-01', 'descricao' => 'Atendimento médico intensivista em UTI adulto e pediátrica'],
            ['nome' => 'Enfermeiro Assistencial', 'sigla' => 'ENF ASS', 'cbo' => '223505', 'escolaridade' => 6, 'gestao' => 0, 'salario' => 6400, 'desconto_hora' => 34, 'inicio' => '2024-01-01', 'descricao' => 'Assistência de enfermagem em unidades clínicas'],
            ['nome' => 'Técnico de Enfermagem', 'sigla' => 'TEC ENF', 'cbo' => '322205', 'escolaridade' => 4, 'gestao' => 0, 'salario' => 3600, 'desconto_hora' => 19, 'inicio' => '2024-01-01', 'descricao' => 'Suporte técnico de enfermagem em plantões hospitalares'],
            ['nome' => 'Fisioterapeuta Hospitalar', 'sigla' => 'FISIO HOSP', 'cbo' => '223605', 'escolaridade' => 6, 'gestao' => 0, 'salario' => 5800, 'desconto_hora' => 31, 'inicio' => '2024-01-01', 'descricao' => 'Fisioterapia respiratória e motora em ambiente hospitalar'],
            ['nome' => 'Farmacêutico Clínico', 'sigla' => 'FARM CLIN', 'cbo' => '223405', 'escolaridade' => 6, 'gestao' => 0, 'salario' => 7200, 'desconto_hora' => 39, 'inicio' => '2024-01-01', 'descricao' => 'Acompanhamento farmacoterapêutico e segurança medicamentosa'],
            ['nome' => 'Recepcionista Hospitalar', 'sigla' => 'RECEP HOSP', 'cbo' => '422105', 'escolaridade' => 4, 'gestao' => 0, 'salario' => 2400, 'desconto_hora' => 13, 'inicio' => '2024-01-01', 'descricao' => 'Atendimento de pacientes e gestão de fluxo na recepção'],
            ['nome' => 'Maqueiro', 'sigla' => 'MAQ', 'cbo' => '515215', 'escolaridade' => 2, 'gestao' => 0, 'salario' => 2100, 'desconto_hora' => 11, 'inicio' => '2024-01-01', 'descricao' => 'Transporte interno e seguro de pacientes entre setores'],
            ['nome' => 'Auxiliar de Limpeza Hospitalar', 'sigla' => 'AUX LIM', 'cbo' => '514320', 'escolaridade' => 2, 'gestao' => 0, 'salario' => 1900, 'desconto_hora' => 10, 'inicio' => '2024-01-01', 'descricao' => 'Higienização e desinfecção de áreas assistenciais'],
            ['nome' => 'Coordenador de Enfermagem', 'sigla' => 'COORD ENF', 'cbo' => '131205', 'escolaridade' => 7, 'gestao' => 1, 'salario' => 9800, 'desconto_hora' => 53, 'inicio' => '2024-01-01', 'descricao' => 'Coordenação de equipes e escalas de enfermagem'],
            ['nome' => 'Gestor Administrativo Hospitalar', 'sigla' => 'GEST ADM', 'cbo' => '131115', 'escolaridade' => 6, 'gestao' => 1, 'salario' => 12500, 'desconto_hora' => 68, 'inicio' => '2024-01-01', 'descricao' => 'Gestão administrativa e integração de processos hospitalares'],
        ];

        foreach ($cargos as $c) {
            $payload = [];
            if (in_array('CARGO_NOME', $cols, true)) {
                $payload['CARGO_NOME'] = $c['nome'];
            }
            if (in_array('CARGO_ATIVO', $cols, true)) {
                $payload['CARGO_ATIVO'] = 1;
            }
            if (in_array('CARGO_SIGLA', $cols, true)) {
                $payload['CARGO_SIGLA'] = $c['sigla'];
            }
            if (in_array('CARGO_CBO', $cols, true)) {
                $payload['CARGO_CBO'] = $c['cbo'];
            } elseif (in_array('CARGO_CODIGO_CBO', $cols, true)) {
                $payload['CARGO_CODIGO_CBO'] = $c['cbo'];
            }
            if (in_array('CARGO_DESCRICAO', $cols, true)) {
                $payload['CARGO_DESCRICAO'] = $c['descricao'];
            }
            if (in_array('CARGO_ESCOLARIDADE', $cols, true)) {
                $payload['CARGO_ESCOLARIDADE'] = $c['escolaridade'];
            }
            if (in_array('CARGO_GESTAO', $cols, true)) {
                $payload['CARGO_GESTAO'] = $c['gestao'];
            }
            if (in_array('CARGO_DATA_INICIO', $cols, true)) {
                $payload['CARGO_DATA_INICIO'] = $c['inicio'];
            }
            if (in_array('CARGO_REMUNERACAO', $cols, true)) {
                $payload['CARGO_REMUNERACAO'] = $c['salario'];
            } elseif (in_array('CARGO_SALARIO', $cols, true)) {
                $payload['CARGO_SALARIO'] = $c['salario'];
            }
            if (in_array('CARGO_VALOR_HORA_DESCONTO', $cols, true)) {
                $payload['CARGO_VALOR_HORA_DESCONTO'] = $c['desconto_hora'];
            }
            if (in_array('created_at', $cols, true)) {
                $payload['created_at'] = now();
            }
            if (in_array('updated_at', $cols, true)) {
                $payload['updated_at'] = now();
            }
            if (in_array('USUARIO_ID', $cols, true)) {
                $payload['USUARIO_ID'] = $adminId;
            }

            DB::table('CARGO')->updateOrInsert(
                ['CARGO_NOME' => $c['nome']],
                $payload
            );
        }
    }

    private function seedDistribuicaoHospitalarFuncionarios(): void
    {
        if (!Schema::hasTable('FUNCIONARIO') || !Schema::hasTable('CARGO')) {
            return;
        }

        $funcCols = Schema::getColumnListing('FUNCIONARIO');
        if (!in_array('CARGO_ID', $funcCols, true)) {
            return;
        }

        $cargos = DB::table('CARGO')
            ->whereIn('CARGO_NOME', [
                'Médico Plantonista UTI',
                'Enfermeiro Assistencial',
                'Técnico de Enfermagem',
                'Fisioterapeuta Hospitalar',
                'Farmacêutico Clínico',
                'Recepcionista Hospitalar',
                'Maqueiro',
                'Auxiliar de Limpeza Hospitalar',
                'Coordenador de Enfermagem',
                'Gestor Administrativo Hospitalar',
            ])
            ->pluck('CARGO_ID')
            ->values();
        if ($cargos->isEmpty()) {
            return;
        }

        $funcionarios = DB::table('FUNCIONARIO')
            ->when(Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM'), fn($q) => $q->whereNull('FUNCIONARIO_DATA_FIM'))
            ->orderBy('FUNCIONARIO_ID')
            ->limit(60)
            ->get(['FUNCIONARIO_ID']);

        foreach ($funcionarios as $idx => $f) {
            $cargoId = (int) $cargos[$idx % $cargos->count()];
            DB::table('FUNCIONARIO')
                ->where('FUNCIONARIO_ID', $f->FUNCIONARIO_ID)
                ->update(['CARGO_ID' => $cargoId]);
        }
    }

    private function seedContratosEVinculosConsistentes(): void
    {
        if (!Schema::hasTable('FUNCIONARIO')) {
            return;
        }

        $funcCols = Schema::getColumnListing('FUNCIONARIO');
        $pessoaCols = Schema::hasTable('PESSOA') ? Schema::getColumnListing('PESSOA') : [];
        $pisCol = in_array('PESSOA_PIS_PASEP', $pessoaCols, true)
            ? 'PESSOA_PIS_PASEP'
            : (in_array('PESSOA_PIS', $pessoaCols, true) ? 'PESSOA_PIS' : null);

        $funcionarios = DB::table('FUNCIONARIO as f')
            ->leftJoin('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->select('f.FUNCIONARIO_ID', 'f.PESSOA_ID', 'f.FUNCIONARIO_MATRICULA')
            ->addSelect($pisCol ? DB::raw("p.$pisCol as PESSOA_PIS_MERGED") : DB::raw('NULL as PESSOA_PIS_MERGED'))
            ->orderBy('f.FUNCIONARIO_ID')
            ->limit(80)
            ->get();

        foreach ($funcionarios as $f) {
            $updateFunc = [];
            if (in_array('FUNCIONARIO_MATRICULA', $funcCols, true) && empty($f->FUNCIONARIO_MATRICULA)) {
                $updateFunc['FUNCIONARIO_MATRICULA'] = 'MAT-' . str_pad((string) $f->FUNCIONARIO_ID, 5, '0', STR_PAD_LEFT);
            }
            if (in_array('FUNCIONARIO_DATA_INICIO', $funcCols, true)) {
                $dtInicio = DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $f->FUNCIONARIO_ID)->value('FUNCIONARIO_DATA_INICIO');
                if (empty($dtInicio)) {
                    $updateFunc['FUNCIONARIO_DATA_INICIO'] = now()->subYears(2)->toDateString();
                }
            }
            if (!empty($updateFunc)) {
                DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $f->FUNCIONARIO_ID)->update($updateFunc);
            }

            if ($pisCol && Schema::hasTable('PESSOA') && empty($f->PESSOA_PIS_MERGED)) {
                $pisNumero = str_pad((string) (10000000000 + (int) $f->FUNCIONARIO_ID), 11, '0', STR_PAD_LEFT);
                DB::table('PESSOA')->where('PESSOA_ID', $f->PESSOA_ID)->update([$pisCol => $pisNumero]);
            }
        }

        if (Schema::hasTable('HISTORICO_FUNCIONAL')) {
            $histCols = Schema::getColumnListing('HISTORICO_FUNCIONAL');
            foreach ($funcionarios->take(40) as $f) {
                $lot = null;
                if (Schema::hasTable('LOTACAO')) {
                    $lot = DB::table('LOTACAO as l')
                        ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                        ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
                        ->leftJoin('VINCULO as v', 'v.VINCULO_ID', '=', 'l.VINCULO_ID')
                        ->where('l.FUNCIONARIO_ID', $f->FUNCIONARIO_ID)
                        ->whereNull('l.LOTACAO_DATA_FIM')
                        ->select('s.SETOR_NOME', 'u.UNIDADE_NOME', 'v.VINCULO_NOME', 'l.LOTACAO_DATA_INICIO')
                        ->first();
                }
                $cargoNome = null;
                if (Schema::hasColumn('FUNCIONARIO', 'CARGO_ID')) {
                    $cargoNome = DB::table('FUNCIONARIO as fx')
                        ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'fx.CARGO_ID')
                        ->where('fx.FUNCIONARIO_ID', $f->FUNCIONARIO_ID)
                        ->value('c.CARGO_NOME');
                }
                $inicio = $lot->LOTACAO_DATA_INICIO ?? DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $f->FUNCIONARIO_ID)->value('FUNCIONARIO_DATA_INICIO') ?? now()->subYears(2)->toDateString();
                $regime = $lot->VINCULO_NOME ?? 'Estatutário';

                $payload = [];
                if (in_array('FUNCIONARIO_ID', $histCols, true)) $payload['FUNCIONARIO_ID'] = $f->FUNCIONARIO_ID;
                if (in_array('HISTORICO_TIPO', $histCols, true)) $payload['HISTORICO_TIPO'] = $regime;
                if (in_array('HISTORICO_REGIME', $histCols, true)) $payload['HISTORICO_REGIME'] = $regime;
                if (in_array('HISTORICO_CARGO', $histCols, true)) $payload['HISTORICO_CARGO'] = $cargoNome ?? 'Servidor';
                if (in_array('HISTORICO_SETOR', $histCols, true)) $payload['HISTORICO_SETOR'] = $lot->SETOR_NOME ?? '—';
                if (in_array('HISTORICO_UNIDADE', $histCols, true)) $payload['HISTORICO_UNIDADE'] = $lot->UNIDADE_NOME ?? '—';
                if (in_array('HISTORICO_DATA_INICIO', $histCols, true)) $payload['HISTORICO_DATA_INICIO'] = $inicio;
                if (in_array('HISTORICO_DATA_FIM', $histCols, true)) $payload['HISTORICO_DATA_FIM'] = null;

                if (!empty($payload)) {
                    $whereKey = ['FUNCIONARIO_ID' => $f->FUNCIONARIO_ID];
                    if (in_array('HISTORICO_DATA_INICIO', $histCols, true)) {
                        $whereKey['HISTORICO_DATA_INICIO'] = $inicio;
                    } elseif (in_array('HISTORICO_CARGO', $histCols, true)) {
                        $whereKey['HISTORICO_CARGO'] = $payload['HISTORICO_CARGO'] ?? 'Servidor';
                    } elseif (in_array('HISTORICO_TIPO', $histCols, true)) {
                        $whereKey['HISTORICO_TIPO'] = $payload['HISTORICO_TIPO'] ?? 'Servidor';
                    }
                    DB::table('HISTORICO_FUNCIONAL')->updateOrInsert($whereKey, $payload);
                }
            }
        }
    }

    private function seedPerfisAcessoPorResponsabilidade(): void
    {
        if (!Schema::hasTable('USUARIO') || !Schema::hasTable('USUARIO_PERFIL')) {
            return;
        }

        $usuarios = DB::table('USUARIO')
            ->where('USUARIO_ATIVO', 1)
            ->orderBy('USUARIO_ID')
            ->limit(40)
            ->get(['USUARIO_ID']);
        if ($usuarios->isEmpty()) {
            return;
        }

        // Mix de perfis para simular hospital real:
        // operacional, manutenção, rh folha, gestão, coordenação, diretor unidade, rh unidade.
        $mixPerfis = [3, 4, 6, 7, 8, 11, 12, 14, 15];

        foreach ($usuarios as $idx => $u) {
            $perfilPrimario = $mixPerfis[$idx % count($mixPerfis)];
            DB::table('USUARIO_PERFIL')->updateOrInsert(
                ['USUARIO_ID' => $u->USUARIO_ID, 'PERFIL_ID' => $perfilPrimario],
                ['USUARIO_PERFIL_ATIVO' => 1]
            );

            // 1 a cada 4 usuários recebe segundo perfil de liderança para cobertura de menus.
            if ($idx % 4 === 0) {
                DB::table('USUARIO_PERFIL')->updateOrInsert(
                    ['USUARIO_ID' => $u->USUARIO_ID, 'PERFIL_ID' => 11],
                    ['USUARIO_PERFIL_ATIVO' => 1]
                );
            }
        }
    }

    private function seedMultilotacaoHospitalar(): void
    {
        if (!Schema::hasTable('LOTACAO') || !Schema::hasTable('SETOR') || !Schema::hasTable('FUNCIONARIO')) {
            return;
        }

        $setores = DB::table('SETOR')
            ->orderBy('SETOR_ID')
            ->limit(12)
            ->pluck('SETOR_ID')
            ->values();
        if ($setores->count() < 2) {
            return;
        }

        $funcionarios = DB::table('FUNCIONARIO')
            ->when(Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM'), fn($q) => $q->whereNull('FUNCIONARIO_DATA_FIM'))
            ->orderBy('FUNCIONARIO_ID')
            ->limit(30)
            ->pluck('FUNCIONARIO_ID')
            ->values();
        if ($funcionarios->isEmpty()) {
            return;
        }

        $lotCols = Schema::getColumnListing('LOTACAO');
        $temFim = in_array('LOTACAO_DATA_FIM', $lotCols, true);
        $temInicio = in_array('LOTACAO_DATA_INICIO', $lotCols, true);
        $temAtivo = in_array('LOTACAO_ATIVO', $lotCols, true);

        foreach ($funcionarios as $idx => $funcionarioId) {
            $setorPrincipal = (int) $setores[$idx % $setores->count()];
            $setorSecundario = (int) $setores[($idx + 3) % $setores->count()];

            $this->upsertLotacao($funcionarioId, $setorPrincipal, $temInicio, $temFim, $temAtivo, true);
            if ($idx % 3 === 0) {
                $this->upsertLotacao($funcionarioId, $setorSecundario, $temInicio, $temFim, $temAtivo, false);
            }
        }
    }

    private function upsertLotacao(
        int $funcionarioId,
        int $setorId,
        bool $temInicio,
        bool $temFim,
        bool $temAtivo,
        bool $principal
    ): void {
        $query = DB::table('LOTACAO')
            ->where('FUNCIONARIO_ID', $funcionarioId)
            ->where('SETOR_ID', $setorId);

        if ($temFim) {
            $query->whereNull('LOTACAO_DATA_FIM');
        }
        $existe = $query->exists();
        if ($existe) {
            return;
        }

        $payload = [
            'FUNCIONARIO_ID' => $funcionarioId,
            'SETOR_ID' => $setorId,
        ];
        if ($temInicio) {
            $payload['LOTACAO_DATA_INICIO'] = $principal
                ? now()->subYears(2)->toDateString()
                : now()->subMonths(6)->toDateString();
        }
        if ($temFim) {
            $payload['LOTACAO_DATA_FIM'] = null;
        }
        if ($temAtivo) {
            $payload['LOTACAO_ATIVO'] = 1;
        }
        DB::table('LOTACAO')->insert($payload);
    }

    private function seedConectividadeCrossModule(int $adminId): void
    {
        if (!Schema::hasTable('FUNCIONARIO')) {
            return;
        }

        $funcionarios = DB::table('FUNCIONARIO')
            ->when(Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM'), fn($q) => $q->whereNull('FUNCIONARIO_DATA_FIM'))
            ->orderBy('FUNCIONARIO_ID')
            ->limit(20)
            ->pluck('FUNCIONARIO_ID')
            ->values();
        if ($funcionarios->isEmpty()) {
            return;
        }

        $competencia = now()->format('Y-m');
        $inicioMes = now()->startOfMonth();

        // Camada anti-regressão de abas vazias:
        // garante coerência mínima entre ponto, banco de horas e escala.
        foreach ($funcionarios as $idx => $fid) {
            if (Schema::hasTable('REGISTRO_PONTO')) {
                $colsRp = Schema::getColumnListing('REGISTRO_PONTO');
                for ($d = 0; $d < 6; $d++) {
                    $dia = $inicioMes->copy()->addDays($d);
                    if ($dia->isWeekend()) {
                        continue;
                    }
                    foreach ([
                        ['entrada', '08:00:00'],
                        ['saida_alm', '12:00:00'],
                        ['ret_alm', '13:00:00'],
                        ['saida', $idx % 2 === 0 ? '17:10:00' : '16:45:00'],
                    ] as [$tipo, $hora]) {
                        $dataHora = $dia->toDateString() . ' ' . $hora;
                        $exists = DB::table('REGISTRO_PONTO')
                            ->where('FUNCIONARIO_ID', $fid)
                            ->where('REGISTRO_TIPO', $tipo)
                            ->where('REGISTRO_DATA_HORA', $dataHora)
                            ->exists();
                        if ($exists) {
                            continue;
                        }
                        $payload = [
                            'FUNCIONARIO_ID' => $fid,
                            'REGISTRO_TIPO' => $tipo,
                            'REGISTRO_DATA_HORA' => $dataHora,
                        ];
                        if (in_array('REGISTRO_ORIGEM', $colsRp, true)) {
                            $payload['REGISTRO_ORIGEM'] = 'SEED';
                        }
                        DB::table('REGISTRO_PONTO')->insert($payload);
                    }
                }
            }

            if (Schema::hasTable('JORNADA_LEDGER')) {
                $totalCred = (float) DB::table('JORNADA_LEDGER')
                    ->where('FUNCIONARIO_ID', $fid)
                    ->where('COMPETENCIA', $competencia)
                    ->sum('HORAS_CREDITADAS');
                $totalDeb = (float) DB::table('JORNADA_LEDGER')
                    ->where('FUNCIONARIO_ID', $fid)
                    ->where('COMPETENCIA', $competencia)
                    ->sum('HORAS_DEBITADAS');

                if (($totalCred + $totalDeb) == 0.0) {
                    DB::table('JORNADA_LEDGER')->insert([
                        'FUNCIONARIO_ID' => (int) $fid,
                        'COMPETENCIA' => $competencia,
                        'JORNADA_DATA' => $inicioMes->copy()->addDays(($idx % 5) + 1)->toDateString(),
                        'LANCAMENTO_TIPO' => $idx % 2 === 0 ? 'credito' : 'debito',
                        'MINUTOS_TRABALHADOS' => $idx % 2 === 0 ? 500 : 450,
                        'MINUTOS_META' => 480,
                        'MINUTOS_DELTA' => $idx % 2 === 0 ? 20 : -30,
                        'HORAS_CREDITADAS' => $idx % 2 === 0 ? 0.33 : 0,
                        'HORAS_DEBITADAS' => $idx % 2 === 0 ? 0 : 0.5,
                        'SALDO_HORAS' => $idx % 2 === 0 ? 0.33 : -0.5,
                        'VERSAO' => 1,
                        'ORIGEM' => 'seed_fase2',
                        'MOTIVO' => 'cross_module_conectividade',
                        'DETALHE' => json_encode(['seed' => true, 'cross_module' => true], JSON_UNESCAPED_UNICODE),
                        'HASH_AUDITORIA' => hash('sha256', 'seed_fase2|' . $fid . '|' . $competencia . '|' . $idx),
                        'GERADO_POR_USUARIO_ID' => $adminId,
                        'GERADO_EM' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if (Schema::hasTable('BANCO_HORAS')) {
                $credMes = (float) DB::table('BANCO_HORAS')
                    ->where('FUNCIONARIO_ID', $fid)
                    ->where('COMPETENCIA', $competencia)
                    ->sum('HORAS_CREDITADAS');
                $debMes = (float) DB::table('BANCO_HORAS')
                    ->where('FUNCIONARIO_ID', $fid)
                    ->where('COMPETENCIA', $competencia)
                    ->sum('HORAS_DEBITADAS');

                if (($credMes + $debMes) == 0.0) {
                    DB::table('BANCO_HORAS')->updateOrInsert(
                        ['FUNCIONARIO_ID' => $fid, 'COMPETENCIA' => $competencia],
                        [
                            'HORAS_CREDITADAS' => $idx % 2 === 0 ? 3.5 : 1.25,
                            'HORAS_DEBITADAS' => $idx % 2 === 0 ? 1.0 : 2.75,
                            'TIPO' => 'APURACAO',
                            'OBSERVACAO' => 'Seed fase 2 - conectividade cross module',
                            'REGISTRADO_POR' => $adminId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }
    }

    private function seedRhWorkflowAvancado(int $adminId): void
    {
        $funcionarios = DB::table('FUNCIONARIO')
            ->when(Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM'), fn($q) => $q->whereNull('FUNCIONARIO_DATA_FIM'))
            ->orderBy('FUNCIONARIO_ID')
            ->limit(12)
            ->pluck('FUNCIONARIO_ID')
            ->values();
        if ($funcionarios->isEmpty()) {
            return;
        }

        if (Schema::hasTable('FERIAS')) {
            foreach ($funcionarios->take(6) as $idx => $fid) {
                $inicio = now()->addDays(20 + ($idx * 3))->toDateString();
                DB::table('FERIAS')->updateOrInsert(
                    ['FUNCIONARIO_ID' => $fid, 'FERIAS_DATA_INICIO' => $inicio],
                    [
                        'FERIAS_DATA_FIM' => now()->addDays(49 + ($idx * 3))->toDateString(),
                        'FERIAS_AQUISITIVO_INICIO' => (int) now()->subYear()->format('Ymd'),
                        'FERIAS_AQUISITIVO_FIM' => (int) now()->format('Ymd'),
                    ]
                );
            }
        }

        if (Schema::hasTable('AFASTAMENTO')) {
            foreach ($funcionarios->take(4) as $idx => $fid) {
                DB::table('AFASTAMENTO')->updateOrInsert(
                    ['FUNCIONARIO_ID' => $fid, 'AFASTAMENTO_DATA_INICIO' => now()->subDays(15 + $idx)->toDateString()],
                    [
                        'AFASTAMENTO_DATA_FIM' => now()->subDays(10 + $idx)->toDateString(),
                        'AFASTAMENTO_TIPO' => ($idx % 3) + 1,
                    ]
                );
            }
        }

        if (Schema::hasTable('AUTOCADASTRO_TOKEN')) {
            foreach ($funcionarios->take(3) as $idx => $fid) {
                $token = hash('sha256', 'seed-autocadastro-' . $fid);
                DB::table('AUTOCADASTRO_TOKEN')->updateOrInsert(
                    ['TOKEN' => $token],
                    [
                        'TOKEN_EMAIL' => "candidato{$idx}@gente.local",
                        'TOKEN_NOME' => "Candidato Seed {$idx}",
                        'FUNCIONARIO_ID' => $fid,
                        'CRIADO_POR' => $adminId,
                        'TOKEN_STATUS' => $idx === 0 ? 'pendente' : ($idx === 1 ? 'preenchido' : 'aprovado'),
                        'TOKEN_DADOS' => json_encode(['origem' => 'seed', 'area' => 'RH'], JSON_UNESCAPED_UNICODE),
                        'expira_em' => now()->addDays(7),
                        'usado_em' => $idx > 0 ? now()->subDays(1) : null,
                        'created_at' => now()->subDays(2),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        if (Schema::hasTable('VERBA_LANCAMENTO') && Schema::hasTable('VERBA_TIPO')) {
            $verbaTipo = DB::table('VERBA_TIPO')->value('VERBA_TIPO_ID');
            if ($verbaTipo) {
                foreach ($funcionarios->take(5) as $idx => $fid) {
                    DB::table('VERBA_LANCAMENTO')->updateOrInsert(
                        ['FUNCIONARIO_ID' => $fid, 'VERBA_TIPO_ID' => $verbaTipo, 'COMPETENCIA' => now()->format('Y-m')],
                        [
                            'VALOR' => 220 + ($idx * 35),
                            'JUSTIFICATIVA' => 'Seed fase 2 — verba indenizatória operacional',
                            'STATUS' => $idx % 2 === 0 ? 'APROVADO' : 'PENDENTE',
                            'LANCADO_POR' => $adminId,
                            'created_at' => now()->subDays(3),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }

        if (Schema::hasTable('RESCISAO_CALCULO')) {
            $fid = (int) $funcionarios->last();
            DB::table('RESCISAO_CALCULO')->updateOrInsert(
                ['FUNCIONARIO_ID' => $fid, 'DATA_EXONERACAO' => now()->subDays(40)->toDateString()],
                [
                    'MOTIVO_SAIDA' => 'EXONERACAO',
                    'PORTARIA_NUM' => 'PT-SEED-2026/04',
                    'DATA_CALCULO' => now()->subDays(38),
                    'CALCULADO_POR' => $adminId,
                    'STATUS' => 'VALIDADO',
                    'SALDO_SALARIO' => 1400,
                    'FERIAS_PROP' => 900,
                    'FERIAS_PROP_TERCIO' => 300,
                    'DECIMO_TERCEIRO_PROP' => 600,
                    'TOTAL_BRUTO' => 3200,
                    'DESCONTO_IRRF' => 120,
                    'TOTAL_LIQUIDO' => 3080,
                    'REGIME_PREV' => 'RPPS',
                    'OBSERVACOES' => 'Seed fase 2 — cenário de rescisão validado',
                    'created_at' => now()->subDays(38),
                    'updated_at' => now(),
                ]
            );
        }

        if (Schema::hasTable('DIARIA_SOLICITACAO') && Schema::hasTable('DIARIA_TABELA')) {
            DB::table('DIARIA_TABELA')->updateOrInsert(
                ['CARGO_NIVEL' => 'NIVEL_SUPERIOR', 'DESTINO_TIPO' => 'OUTRA_CAPITAL', 'VIGENCIA_INICIO' => now()->startOfYear()->toDateString()],
                ['VALOR_DIARIA' => 420, 'VIGENCIA_FIM' => null]
            );
            foreach ($funcionarios->take(3) as $idx => $fid) {
                $solId = DB::table('DIARIA_SOLICITACAO')->where('FUNCIONARIO_ID', $fid)->where('DESTINO', 'São Paulo - SP')->value('SOLICITACAO_ID');
                if (!$solId) {
                    $solId = DB::table('DIARIA_SOLICITACAO')->insertGetId([
                        'FUNCIONARIO_ID' => $fid,
                        'DESTINO' => 'São Paulo - SP',
                        'DESTINO_TIPO' => 'OUTRA_CAPITAL',
                        'OBJETIVO' => 'Capacitação hospitalar e integração de protocolos.',
                        'DATA_IDA' => now()->addDays(10 + $idx),
                        'DATA_VOLTA' => now()->addDays(12 + $idx),
                        'QTDE_DIARIAS' => 2.5,
                        'VALOR_TOTAL' => 1050,
                        'PORTARIA_NUM' => 'DIARIA-SEED-' . ($idx + 1),
                        'STATUS' => $idx === 0 ? 'APROVADA' : 'PENDENTE',
                        'APROVADO_POR' => $idx === 0 ? $adminId : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                if (Schema::hasTable('DIARIA_PRESTACAO') && $idx === 0) {
                    DB::table('DIARIA_PRESTACAO')->updateOrInsert(
                        ['SOLICITACAO_ID' => $solId],
                        [
                            'COMPROVANTE_PATH' => '/storage/comprovantes/diaria-seed.pdf',
                            'VALOR_GASTO' => 980,
                            'SALDO_DEVOLVIDO' => 70,
                            'DATA_PRESTACAO' => now()->addDays(14),
                            'OBSERVACAO' => 'Prestação seed para fluxo completo.',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }

        if (Schema::hasTable('ESTAGIARIO') && Schema::hasTable('ESTAGIO_CONTRATO')) {
            $setorId = Schema::hasTable('SETOR') ? DB::table('SETOR')->value('SETOR_ID') : null;
            $unidadeId = Schema::hasTable('UNIDADE') ? DB::table('UNIDADE')->value('UNIDADE_ID') : null;
            $cpf = '99988877766';
            $estagiarioId = DB::table('ESTAGIARIO')->where('CPF', $cpf)->value('ESTAGIARIO_ID');
            if (!$estagiarioId) {
                $estagiarioId = DB::table('ESTAGIARIO')->insertGetId([
                    'CPF' => $cpf,
                    'NOME' => 'Estagiário Seed Hospitalar',
                    'INSTITUICAO_ENSINO' => 'UFMA',
                    'AGENTE_INTEGRACAO' => 'CIEE',
                    'CURSO' => 'Administração Hospitalar',
                    'PERIODO_LETIVO' => '6',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $contratoId = DB::table('ESTAGIO_CONTRATO')->where('ESTAGIARIO_ID', $estagiarioId)->value('CONTRATO_ID');
            if (!$contratoId) {
                $contratoId = DB::table('ESTAGIO_CONTRATO')->insertGetId([
                    'ESTAGIARIO_ID' => $estagiarioId,
                    'SETOR_ID' => $setorId,
                    'UNIDADE_ID' => $unidadeId,
                    'SUPERVISOR_ID' => $funcionarios->first(),
                    'DATA_INICIO' => now()->subMonths(2)->toDateString(),
                    'DATA_FIM' => now()->addMonths(8)->toDateString(),
                    'CARGA_HR_DIA' => 6,
                    'BOLSA_VALOR' => 900,
                    'AUXILIO_TRANSPORTE' => 160,
                    'STATUS' => 'ATIVO',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if (Schema::hasTable('ESTAGIO_FREQUENCIA')) {
                DB::table('ESTAGIO_FREQUENCIA')->updateOrInsert(
                    ['CONTRATO_ID' => $contratoId, 'MES_REF' => now()->format('Y-m')],
                    ['DIAS_PRESENTES' => 19, 'DIAS_FALTAS' => 1, 'BOLSA_CALCULADA' => 855, 'STATUS' => 'APROVADO', 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        if (Schema::hasTable('PSS_EDITAL') && Schema::hasTable('PSS_VAGA') && Schema::hasTable('PSS_CANDIDATO')) {
            $editalId = DB::table('PSS_EDITAL')->where('NUMERO_EDITAL', 'PSS-2026-01')->value('EDITAL_ID');
            if (!$editalId) {
                $editalId = DB::table('PSS_EDITAL')->insertGetId([
                    'TITULO' => 'PSS Assistencial 2026',
                    'TIPO' => 'PSS',
                    'DATA_ABERTURA' => now()->subDays(20)->toDateString(),
                    'DATA_FECHAMENTO' => now()->addDays(15)->toDateString(),
                    'NUMERO_EDITAL' => 'PSS-2026-01',
                    'STATUS' => 'PUBLICADO',
                    'OBSERVACOES' => 'Seed fase 2 para testes de painel RH.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $vagaId = DB::table('PSS_VAGA')->where('EDITAL_ID', $editalId)->where('CARGO', 'Enfermeiro')->value('VAGA_ID');
            if (!$vagaId) {
                $vagaId = DB::table('PSS_VAGA')->insertGetId([
                    'EDITAL_ID' => $editalId,
                    'CARGO' => 'Enfermeiro',
                    'LOTACAO' => 'UTI',
                    'VAGAS' => 8,
                    'VAGAS_RESERVA_PCD' => 1,
                    'SALARIO' => 6400,
                ]);
            }
            DB::table('PSS_CANDIDATO')->updateOrInsert(
                ['CPF' => '11122233344', 'EDITAL_ID' => $editalId],
                [
                    'NOME' => 'Candidata Seed UTI',
                    'VAGA_ID' => $vagaId,
                    'INSCRICAO_NUM' => 'PSS26001',
                    'NOTA_FINAL' => 87.5,
                    'CLASSIFICACAO' => 2,
                    'STATUS' => 'CONVOCADO',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        if (Schema::hasTable('TERCEIRO_EMPRESA') && Schema::hasTable('TERCEIRO_POSTO') && Schema::hasTable('TERCEIRO_CHECKLIST')) {
            $empresaId = DB::table('TERCEIRO_EMPRESA')->where('CNPJ', '12.345.678/0001-90')->value('EMPRESA_ID');
            if (!$empresaId) {
                $empresaId = DB::table('TERCEIRO_EMPRESA')->insertGetId([
                    'RAZAO_SOCIAL' => 'ServPrime Apoio Hospitalar Ltda',
                    'CNPJ' => '12.345.678/0001-90',
                    'CONTRATO_NUM' => 'CT-TERC-2026/02',
                    'VIGENCIA_INICIO' => now()->subMonths(3)->toDateString(),
                    'VIGENCIA_FIM' => now()->addMonths(9)->toDateString(),
                    'VALOR_MENSAL' => 285000,
                    'FISCAL_ID' => $funcionarios->first(),
                    'STATUS' => 'ATIVO',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('TERCEIRO_POSTO')->updateOrInsert(
                ['EMPRESA_ID' => $empresaId, 'FUNCAO' => 'Auxiliar de Higienização', 'TRABALHADOR_CPF' => '22233344455'],
                ['LOCALIDADE' => 'Hospital Municipal', 'TURNO' => '12x36', 'TRABALHADOR_NOME' => 'Terceiro Seed 1']
            );
            DB::table('TERCEIRO_CHECKLIST')->updateOrInsert(
                ['EMPRESA_ID' => $empresaId, 'COMPETENCIA' => now()->format('Y-m'), 'ITEM' => 'FGTS quitado'],
                ['STATUS_OK' => true, 'GLOSA_VALOR' => 0, 'created_at' => now(), 'updated_at' => now()]
            );
            DB::table('TERCEIRO_CHECKLIST')->updateOrInsert(
                ['EMPRESA_ID' => $empresaId, 'COMPETENCIA' => now()->format('Y-m'), 'ITEM' => 'INSS recolhido'],
                ['STATUS_OK' => false, 'GLOSA_VALOR' => 8400, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function seedDesenvolvimentoSaudeOcupacional(int $adminId): void
    {
        $funcionarios = DB::table('FUNCIONARIO')->orderBy('FUNCIONARIO_ID')->limit(10)->pluck('FUNCIONARIO_ID')->values();
        if ($funcionarios->isEmpty()) {
            return;
        }

        if (Schema::hasTable('TREINAMENTO') && Schema::hasTable('TREINAMENTO_INSCRICAO')) {
            $treinoId = DB::table('TREINAMENTO')->where('TREINAMENTO_TITULO', 'Segurança Assistencial em UTI')->value('TREINAMENTO_ID');
            if (!$treinoId) {
                $treinoId = DB::table('TREINAMENTO')->insertGetId([
                    'TREINAMENTO_TITULO' => 'Segurança Assistencial em UTI',
                    'TREINAMENTO_DESC' => 'Boas práticas e resposta rápida em ambiente crítico.',
                    'TREINAMENTO_AREA' => 'Saúde',
                    'TREINAMENTO_CARGA' => 12,
                    'TREINAMENTO_MODALIDADE' => 'Híbrido',
                    'TREINAMENTO_PROXIMA' => now()->addDays(12)->format('M/Y'),
                    'TREINAMENTO_VAGAS' => 40,
                    'TREINAMENTO_ATIVO' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            foreach ($funcionarios->take(6) as $idx => $fid) {
                DB::table('TREINAMENTO_INSCRICAO')->updateOrInsert(
                    ['TREINAMENTO_ID' => $treinoId, 'FUNCIONARIO_ID' => $fid],
                    [
                        'INSCRICAO_STATUS' => $idx < 2 ? 'concluido' : ($idx < 4 ? 'andamento' : 'inscrito'),
                        'INSCRICAO_PROGRESSO' => $idx < 2 ? 100 : ($idx < 4 ? 65 : 10),
                        'INSCRICAO_DATA_CONCLUSAO' => $idx < 2 ? now()->subDays(5)->toDateString() : null,
                        'INSCRICAO_CERTIFICADO' => $idx < 2,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        if (Schema::hasTable('AVALIACAO_DESEMPENHO') && Schema::hasTable('AVALIACAO_CRITERIO')) {
            $fid = (int) $funcionarios->first();
            $avaliacaoId = DB::table('AVALIACAO_DESEMPENHO')
                ->where('FUNCIONARIO_ID', $fid)
                ->where('AVALIACAO_CICLO', '2026.1')
                ->value('AVALIACAO_ID');
            if (!$avaliacaoId) {
                $avaliacaoId = DB::table('AVALIACAO_DESEMPENHO')->insertGetId([
                    'FUNCIONARIO_ID' => $fid,
                    'AVALIACAO_CICLO' => '2026.1',
                    'AVALIACAO_NOTA_FINAL' => 8.7,
                    'AVALIACAO_STATUS' => 'publicada',
                    'AVALIADOR_ID' => $adminId,
                    'AVALIACAO_OBS' => 'Desempenho consistente com foco assistencial.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            foreach ([
                ['nome' => 'Produtividade', 'peso' => 30, 'nota' => 9],
                ['nome' => 'Qualidade Técnica', 'peso' => 40, 'nota' => 8],
                ['nome' => 'Trabalho em Equipe', 'peso' => 30, 'nota' => 9],
            ] as $c) {
                DB::table('AVALIACAO_CRITERIO')->updateOrInsert(
                    ['AVALIACAO_ID' => $avaliacaoId, 'CRITERIO_NOME' => $c['nome']],
                    ['CRITERIO_PESO' => $c['peso'], 'CRITERIO_NOTA' => $c['nota'], 'CRITERIO_OBS' => 'Seed fase 2', 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        if (Schema::hasTable('SEGURANCA_EPI')) {
            DB::table('SEGURANCA_EPI')->updateOrInsert(
                ['FUNCIONARIO_ID' => $funcionarios->first(), 'EPI_NOME' => 'Máscara N95'],
                ['EPI_CA' => 'CA-44556', 'EPI_ICONE' => '😷', 'EPI_VALIDADE' => now()->addMonths(10)->toDateString(), 'EPI_QUANTIDADE' => 3, 'created_at' => now(), 'updated_at' => now()]
            );
        }
        if (Schema::hasTable('SEGURANCA_INCIDENTE')) {
            DB::table('SEGURANCA_INCIDENTE')->updateOrInsert(
                ['FUNCIONARIO_ID' => $funcionarios->first(), 'INCIDENTE_DATA' => now()->subDays(9)->toDateString(), 'INCIDENTE_LOCAL' => 'UTI Adulto'],
                ['INCIDENTE_TIPO' => 'quase', 'INCIDENTE_DESCRICAO' => 'Quase incidente com queda de frasco, sem dano ao paciente.', 'INCIDENTE_FECHADO' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function seedMedicinaOcupacionalDetalhada(int $adminId): void
    {
        if (!Schema::hasTable('FUNCIONARIO')) {
            return;
        }

        $funcionarios = DB::table('FUNCIONARIO')
            ->orderBy('FUNCIONARIO_ID')
            ->limit(8)
            ->pluck('FUNCIONARIO_ID')
            ->values();
        if ($funcionarios->isEmpty()) {
            return;
        }

        if (Schema::hasTable('EXAME_OCUPACIONAL')) {
            $tipos = ['Periódico', 'Retorno ao Trabalho', 'Mudança de Função', 'Demissional'];
            foreach ($funcionarios as $idx => $fid) {
                $tipo = $tipos[$idx % count($tipos)];
                $realizado = now()->subDays(80 - ($idx * 4))->toDateString();
                $vencimento = now()->addDays(200 + ($idx * 8))->toDateString();
                DB::table('EXAME_OCUPACIONAL')->updateOrInsert(
                    ['FUNCIONARIO_ID' => $fid, 'EXAME_TIPO' => $tipo, 'EXAME_DATA_REALIZACAO' => $realizado],
                    [
                        'EXAME_SUBTIPO' => $idx % 2 === 0 ? 'Clínico Ocupacional' : 'Complementar',
                        'EXAME_DATA_VENCIMENTO' => $vencimento,
                        'EXAME_MEDICO' => $idx % 2 === 0 ? 'Dr. Paulo SESMT' : 'Dra. Helena Medicina',
                        'EXAME_APTO' => 1,
                        'EXAME_OBS' => 'Seed ocupacional para acompanhamento preventivo.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        if (Schema::hasTable('AGENDAMENTO_EXAME')) {
            foreach ($funcionarios->take(5) as $idx => $fid) {
                DB::table('AGENDAMENTO_EXAME')->updateOrInsert(
                    [
                        'FUNCIONARIO_ID' => $fid,
                        'AGENDAMENTO_TIPO' => $idx % 2 === 0 ? 'Periódico' : 'Retorno ao Trabalho',
                        'AGENDAMENTO_DATA' => now()->addDays(5 + ($idx * 3))->toDateString(),
                    ],
                    [
                        'AGENDAMENTO_OBS' => 'Agendamento seed para fila de medicina ocupacional.',
                        'AGENDAMENTO_STATUS' => $idx < 2 ? 'confirmado' : 'pendente',
                        'AGENDAMENTO_DT_SOLICITACAO' => now()->subDays(2 + $idx)->toDateString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        if (Schema::hasTable('HISTORICO_EXAME')) {
            foreach ($funcionarios->take(4) as $idx => $fid) {
                DB::table('HISTORICO_EXAME')->updateOrInsert(
                    ['FUNCIONARIO_ID' => $fid, 'HISTORICO_EXAME_DATA' => now()->subDays(45 + $idx)->toDateString()],
                    [
                        'HISTORICO_EXAME_TIPO' => 'Periódico',
                        'HISTORICO_EXAME_RESULTADO' => 'APTO',
                        'HISTORICO_EXAME_OBS' => 'Histórico seed para trilha ocupacional.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    private function seedSegurancaTrabalhoDetalhada(int $adminId): void
    {
        if (!Schema::hasTable('FUNCIONARIO')) {
            return;
        }

        $funcionarios = DB::table('FUNCIONARIO')
            ->orderBy('FUNCIONARIO_ID')
            ->limit(10)
            ->pluck('FUNCIONARIO_ID')
            ->values();
        if ($funcionarios->isEmpty()) {
            return;
        }

        if (Schema::hasTable('SEGURANCA_EPI')) {
            $epis = [
                ['nome' => 'Máscara N95', 'ca' => 'CA-44556', 'icone' => '😷', 'qtd' => 3],
                ['nome' => 'Óculos de Proteção', 'ca' => 'CA-33210', 'icone' => '🥽', 'qtd' => 1],
                ['nome' => 'Luva de Procedimento', 'ca' => 'CA-55421', 'icone' => '🧤', 'qtd' => 20],
            ];
            foreach ($funcionarios->take(6) as $idx => $fid) {
                $epi = $epis[$idx % count($epis)];
                DB::table('SEGURANCA_EPI')->updateOrInsert(
                    ['FUNCIONARIO_ID' => $fid, 'EPI_NOME' => $epi['nome']],
                    [
                        'EPI_CA' => $epi['ca'],
                        'EPI_ICONE' => $epi['icone'],
                        'EPI_VALIDADE' => now()->addMonths(6 + $idx)->toDateString(),
                        'EPI_QUANTIDADE' => $epi['qtd'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        if (Schema::hasTable('SEGURANCA_INCIDENTE')) {
            $tipos = ['quase', 'com_afastamento', 'sem_afastamento'];
            foreach ($funcionarios->take(5) as $idx => $fid) {
                DB::table('SEGURANCA_INCIDENTE')->updateOrInsert(
                    [
                        'FUNCIONARIO_ID' => $fid,
                        'INCIDENTE_DATA' => now()->subDays(12 + ($idx * 2))->toDateString(),
                        'INCIDENTE_LOCAL' => $idx % 2 === 0 ? 'UTI Adulto' : 'Pronto Atendimento',
                    ],
                    [
                        'INCIDENTE_TIPO' => $tipos[$idx % count($tipos)],
                        'INCIDENTE_DESCRICAO' => 'Ocorrência seed para rastreabilidade SESMT e plano de ação.',
                        'INCIDENTE_FECHADO' => $idx % 2 === 0 ? 1 : 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    private function seedTreinamentosDetalhados(int $adminId): void
    {
        if (!Schema::hasTable('TREINAMENTO') || !Schema::hasTable('TREINAMENTO_INSCRICAO') || !Schema::hasTable('FUNCIONARIO')) {
            return;
        }

        $funcionarios = DB::table('FUNCIONARIO')
            ->orderBy('FUNCIONARIO_ID')
            ->limit(12)
            ->pluck('FUNCIONARIO_ID')
            ->values();
        if ($funcionarios->isEmpty()) {
            return;
        }

        $catalogo = [
            ['titulo' => 'NR-32 Biossegurança Hospitalar', 'area' => 'SESMT', 'carga' => 8, 'modalidade' => 'Presencial', 'vagas' => 35],
            ['titulo' => 'Prevenção de Quedas em Pacientes', 'area' => 'Assistencial', 'carga' => 6, 'modalidade' => 'EAD', 'vagas' => 50],
            ['titulo' => 'Protocolo de Incidente com Perfurocortante', 'area' => 'SESMT', 'carga' => 4, 'modalidade' => 'Híbrido', 'vagas' => 30],
        ];

        foreach ($catalogo as $cidx => $curso) {
            $treinoId = DB::table('TREINAMENTO')
                ->where('TREINAMENTO_TITULO', $curso['titulo'])
                ->value('TREINAMENTO_ID');
            if (!$treinoId) {
                $treinoId = DB::table('TREINAMENTO')->insertGetId([
                    'TREINAMENTO_TITULO' => $curso['titulo'],
                    'TREINAMENTO_DESC' => 'Seed conectado à trilha SESMT/RH.',
                    'TREINAMENTO_AREA' => $curso['area'],
                    'TREINAMENTO_CARGA' => $curso['carga'],
                    'TREINAMENTO_MODALIDADE' => $curso['modalidade'],
                    'TREINAMENTO_PROXIMA' => now()->addDays(7 + ($cidx * 6))->format('M/Y'),
                    'TREINAMENTO_VAGAS' => $curso['vagas'],
                    'TREINAMENTO_ATIVO' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($funcionarios->slice($cidx, 6) as $idx => $fid) {
                DB::table('TREINAMENTO_INSCRICAO')->updateOrInsert(
                    ['TREINAMENTO_ID' => $treinoId, 'FUNCIONARIO_ID' => $fid],
                    [
                        'INSCRICAO_STATUS' => $idx < 2 ? 'concluido' : ($idx < 4 ? 'andamento' : 'inscrito'),
                        'INSCRICAO_PROGRESSO' => $idx < 2 ? 100 : ($idx < 4 ? 70 : 20),
                        'INSCRICAO_DATA_CONCLUSAO' => $idx < 2 ? now()->subDays(4 + $idx)->toDateString() : null,
                        'INSCRICAO_CERTIFICADO' => $idx < 2 ? 1 : 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    private function seedAvaliacoesDesempenhoDetalhadas(int $adminId): void
    {
        if (!Schema::hasTable('AVALIACAO_DESEMPENHO') || !Schema::hasTable('AVALIACAO_CRITERIO') || !Schema::hasTable('FUNCIONARIO')) {
            return;
        }

        $funcionarios = DB::table('FUNCIONARIO')
            ->orderBy('FUNCIONARIO_ID')
            ->limit(6)
            ->pluck('FUNCIONARIO_ID')
            ->values();
        if ($funcionarios->isEmpty()) {
            return;
        }

        $ciclos = ['2025.2', '2026.1'];
        foreach ($funcionarios as $fidx => $fid) {
            foreach ($ciclos as $cidx => $ciclo) {
                $nota = 7.4 + (($fidx + $cidx) % 4) * 0.5;
                $avaliacaoId = DB::table('AVALIACAO_DESEMPENHO')
                    ->where('FUNCIONARIO_ID', $fid)
                    ->where('AVALIACAO_CICLO', $ciclo)
                    ->value('AVALIACAO_ID');
                if (!$avaliacaoId) {
                    $avaliacaoId = DB::table('AVALIACAO_DESEMPENHO')->insertGetId([
                        'FUNCIONARIO_ID' => $fid,
                        'AVALIACAO_CICLO' => $ciclo,
                        'AVALIACAO_NOTA_FINAL' => round($nota, 1),
                        'AVALIACAO_STATUS' => $ciclo === '2026.1' ? 'publicada' : 'encerrada',
                        'AVALIADOR_ID' => $adminId,
                        'AVALIACAO_OBS' => 'Seed de ciclo avaliativo para trilha de evolução.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                foreach ([
                    ['nome' => 'Entrega de Metas', 'peso' => 35, 'nota' => min(10, $nota + 0.3)],
                    ['nome' => 'Qualidade Assistencial', 'peso' => 40, 'nota' => $nota],
                    ['nome' => 'Disciplina e Frequência', 'peso' => 25, 'nota' => max(6, $nota - 0.4)],
                ] as $criterio) {
                    DB::table('AVALIACAO_CRITERIO')->updateOrInsert(
                        ['AVALIACAO_ID' => $avaliacaoId, 'CRITERIO_NOME' => $criterio['nome']],
                        [
                            'CRITERIO_PESO' => $criterio['peso'],
                            'CRITERIO_NOTA' => (int) round($criterio['nota']),
                            'CRITERIO_OBS' => 'Critério seed conectado com RH e progressão.',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }
    }

    private function seedAbonoFaltasHistorico(int $adminId): void
    {
        if (!Schema::hasTable('ABONO_FALTA') || !Schema::hasTable('FUNCIONARIO')) {
            return;
        }
        $cols = Schema::getColumnListing('ABONO_FALTA');

        $funcionarios = DB::table('FUNCIONARIO')
            ->orderBy('FUNCIONARIO_ID')
            ->limit(10)
            ->pluck('FUNCIONARIO_ID')
            ->values();
        if ($funcionarios->isEmpty()) {
            return;
        }

        $tipos = ['medico', 'declaracao', 'servico', 'luto'];
        $statusList = ['pendente', 'aprovado', 'reprovado'];
        $tipoDatatype = DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_NAME', 'ABONO_FALTA')
            ->where('COLUMN_NAME', 'ABONO_FALTA_TIPO')
            ->value('DATA_TYPE');
        $statusDatatype = DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_NAME', 'ABONO_FALTA')
            ->where('COLUMN_NAME', 'ABONO_FALTA_STATUS')
            ->value('DATA_TYPE');

        foreach ($funcionarios->take(8) as $idx => $fid) {
            $data = now()->subDays(3 + $idx)->toDateString();
            $tipoRaw = $tipos[$idx % count($tipos)];
            $statusRaw = $statusList[$idx % count($statusList)];
            $tipo = in_array((string) $tipoDatatype, ['tinyint', 'smallint', 'int', 'bigint'], true)
                ? ($idx % count($tipos)) + 1
                : $tipoRaw;
            $status = in_array((string) $statusDatatype, ['tinyint', 'smallint', 'int', 'bigint'], true)
                ? ($idx % count($statusList)) + 1
                : $statusRaw;

            DB::table('ABONO_FALTA')->updateOrInsert(
                ['FUNCIONARIO_ID' => $fid, 'ABONO_FALTA_DATA_INICIO' => $data],
                array_filter([
                    'ABONO_FALTA_DATA_FIM' => $data,
                    'ABONO_FALTA_JUSTIFICATIVA' => 'Seed de histórico de abono para análise da gestão e trilha de frequência.',
                    'ABONO_FALTA_TIPO' => $tipo,
                    'ABONO_FALTA_STATUS' => $status,
                    'ABONO_FALTA_COMPROVANTE' => null,
                    'USUARIO_ID' => in_array('USUARIO_ID', $cols, true) ? $adminId : null,
                    'created_at' => in_array('created_at', $cols, true) ? now()->subDays($idx) : null,
                    'updated_at' => in_array('updated_at', $cols, true) ? now() : null,
                ], fn($v) => $v !== null)
            );
        }
    }

    private function seedPatrimonioEFrotas(int $adminId): void
    {
        if (Schema::hasTable('BEM_PATRIMONIAL')) {
            $bemId = DB::table('BEM_PATRIMONIAL')->where('BEM_NUMERO', 'TOMB-2026-0001')->value('BEM_ID');
            if (!$bemId) {
                $bemId = DB::table('BEM_PATRIMONIAL')->insertGetId([
                    'BEM_NUMERO' => 'TOMB-2026-0001',
                    'BEM_DESCRICAO' => 'Monitor multiparamétrico UTI',
                    'BEM_CATEGORIA' => 'EQUIPAMENTO',
                    'BEM_VALOR_AQUISICAO' => 19800,
                    'BEM_DATA_AQUISICAO' => now()->subMonths(4)->toDateString(),
                    'BEM_VALOR_ATUAL' => 18600,
                    'BEM_ESTADO' => 'OTIMO',
                    'BEM_STATUS' => 'ATIVO',
                    'UO_ID' => 1,
                    'SERVIDOR_ID' => null,
                    'BEM_VIDA_UTIL_ANOS' => 8,
                    'BEM_VALOR_RESIDUAL' => 1000,
                    'BEM_DEPRECIACAO_ACUMULADA' => 1200,
                    'BEM_DATA_ULTIMA_DEPRECIACAO' => now()->subMonth()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if (Schema::hasTable('MOVIMENTACAO_PATRIMONIAL')) {
                DB::table('MOVIMENTACAO_PATRIMONIAL')->updateOrInsert(
                    ['BEM_ID' => $bemId, 'MOV_DATA' => now()->subDays(12)->toDateString(), 'MOV_TIPO' => 'TRANSFERENCIA'],
                    ['UO_ORIGEM_ID' => 1, 'UO_DESTINO_ID' => 2, 'MOV_MOTIVO' => 'Redistribuição de ativos para ampliar cobertura UTI.', 'REGISTRADO_POR' => $adminId, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        if (Schema::hasTable('VEICULO')) {
            $veiculosSeed = [
                ['placa' => 'QWE2A34', 'modelo' => 'Sprinter UTI Móvel', 'marca' => 'Mercedes', 'ano' => 2024, 'tipo' => 'AMBULANCIA', 'status' => 'EM_USO', 'km' => 42350, 'prox' => now()->addDays(2)->toDateString(), 'cor' => 'Branca', 'renavam' => '12345678901'],
                ['placa' => 'PTS1C55', 'modelo' => 'Ducato Van', 'marca' => 'Fiat', 'ano' => 2023, 'tipo' => 'VAN', 'status' => 'DISPONIVEL', 'km' => 31240, 'prox' => now()->addDays(9)->toDateString(), 'cor' => 'Prata', 'renavam' => '12345678902'],
                ['placa' => 'MOB9D10', 'modelo' => 'Onix LT', 'marca' => 'Chevrolet', 'ano' => 2022, 'tipo' => 'CARRO', 'status' => 'DISPONIVEL', 'km' => 27890, 'prox' => now()->addDays(28)->toDateString(), 'cor' => 'Branco', 'renavam' => '12345678903'],
                ['placa' => 'HSP5E72', 'modelo' => 'Ranger XLS', 'marca' => 'Ford', 'ano' => 2021, 'tipo' => 'CAMINHAO', 'status' => 'EM_MANUTENCAO', 'km' => 88770, 'prox' => now()->subDays(1)->toDateString(), 'cor' => 'Azul', 'renavam' => '12345678904'],
            ];
            $veiculoIds = [];
            foreach ($veiculosSeed as $v) {
                DB::table('VEICULO')->updateOrInsert(
                    ['VEICULO_PLACA' => $v['placa']],
                    [
                        'VEICULO_MODELO' => $v['modelo'],
                        'VEICULO_MARCA' => $v['marca'],
                        'VEICULO_ANO' => $v['ano'],
                        'VEICULO_TIPO' => $v['tipo'],
                        'VEICULO_STATUS' => $v['status'],
                        'UO_ID' => 1,
                        'VEICULO_KM_ATUAL' => $v['km'],
                        'VEICULO_PROX_MANUTENCAO' => $v['prox'],
                        'VEICULO_COR' => $v['cor'],
                        'VEICULO_RENAVAM' => $v['renavam'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $veiculoIds[$v['placa']] = (int) DB::table('VEICULO')->where('VEICULO_PLACA', $v['placa'])->value('VEICULO_ID');
            }

            if (Schema::hasTable('SAIDA_VEICULO')) {
                $motoristaId = (int) (DB::table('FUNCIONARIO')->value('FUNCIONARIO_ID') ?: 1);
                DB::table('SAIDA_VEICULO')->updateOrInsert(
                    ['VEICULO_ID' => $veiculoIds['QWE2A34'] ?? 0, 'SAIDA_DATA_HORA' => now()->subHours(6)],
                    [
                        'MOTORISTA_ID' => $motoristaId,
                        'UO_SOLICITANTE_ID' => 1,
                        'SAIDA_DESTINO' => 'Hospital de Referência',
                        'SAIDA_FINALIDADE' => 'Transferência inter-hospitalar de paciente crítico',
                        'RETORNO_DATA_HORA' => now()->subHours(3),
                        'KM_SAIDA' => 42180,
                        'KM_RETORNO' => 42350,
                        'KM_PERCORRIDO' => 170,
                        'REGISTRADO_POR' => $adminId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                DB::table('SAIDA_VEICULO')->updateOrInsert(
                    ['VEICULO_ID' => $veiculoIds['PTS1C55'] ?? 0, 'SAIDA_DATA_HORA' => now()->subHours(2)],
                    [
                        'MOTORISTA_ID' => $motoristaId,
                        'UO_SOLICITANTE_ID' => 1,
                        'SAIDA_DESTINO' => 'Unidade de Pronto Atendimento',
                        'SAIDA_FINALIDADE' => 'Entrega de materiais críticos',
                        'RETORNO_DATA_HORA' => null,
                        'KM_SAIDA' => 31195,
                        'KM_RETORNO' => null,
                        'KM_PERCORRIDO' => null,
                        'REGISTRADO_POR' => $adminId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
            if (Schema::hasTable('MANUTENCAO_VEICULO')) {
                DB::table('MANUTENCAO_VEICULO')->updateOrInsert(
                    ['VEICULO_ID' => $veiculoIds['QWE2A34'] ?? 0, 'MANUT_DATA' => now()->subDays(25)->toDateString(), 'MANUT_TIPO' => 'PREVENTIVA'],
                    [
                        'MANUT_DESCRICAO' => 'Revisão preventiva completa e troca de óleo.',
                        'MANUT_VALOR' => 1850,
                        'MANUT_PROXIMA' => now()->addMonths(4)->toDateString(),
                        'MANUT_FORNECEDOR' => 'Oficina Saúde Móvel',
                        'REGISTRADO_POR' => $adminId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                DB::table('MANUTENCAO_VEICULO')->updateOrInsert(
                    ['VEICULO_ID' => $veiculoIds['HSP5E72'] ?? 0, 'MANUT_DATA' => now()->subDays(4)->toDateString(), 'MANUT_TIPO' => 'CORRETIVA'],
                    [
                        'MANUT_DESCRICAO' => 'Troca de pastilhas e correção do sistema de freio.',
                        'MANUT_VALOR' => 3420,
                        'MANUT_PROXIMA' => now()->addDays(30)->toDateString(),
                        'MANUT_FORNECEDOR' => 'Oficina FleetCare',
                        'REGISTRADO_POR' => $adminId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    private function seedComplianceEControleExterno(int $adminId): void
    {
        $funcId = (int) (DB::table('FUNCIONARIO')->value('FUNCIONARIO_ID') ?: 1);

        if (Schema::hasTable('ESOCIAL_EVENTO')) {
            DB::table('ESOCIAL_EVENTO')->updateOrInsert(
                ['FUNCIONARIO_ID' => $funcId, 'TIPO_EVENTO' => 'S-1200', 'COMPETENCIA' => now()->format('Y-m')],
                [
                    'DATA_REFERENCIA' => now()->toDateString(),
                    'STATUS' => 'GERADO',
                    'XML_GERADO' => '<esocial><evento>S-1200</evento></esocial>',
                    'RETORNO_GOVERNO' => null,
                    'MOTIVO_ERRO' => null,
                    'DT_ENVIO' => null,
                    'GERADO_POR' => $adminId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        if (Schema::hasTable('SICONFI_ENVIO')) {
            DB::table('SICONFI_ENVIO')->updateOrInsert(
                ['ENVIO_TIPO' => 'SICONFI_RREO', 'ENVIO_ANO' => (int) now()->format('Y'), 'ENVIO_MES' => (int) now()->format('m')],
                [
                    'ENVIO_BIMESTRE' => (int) ceil(((int) now()->format('m')) / 2),
                    'ENVIO_STATUS' => 'ENVIADO',
                    'ENVIO_ARQUIVO' => 'rreo_' . now()->format('Ym') . '.xml',
                    'ENVIO_OBSERVACAO' => 'Seed fase 2 — envio validado',
                    'USUARIO_ID' => $adminId,
                    'ENVIO_DT_GERACAO' => now()->subHours(4),
                    'ENVIO_DT_TRANSMISSAO' => now()->subHours(3),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        if (Schema::hasTable('RGF_DADOS')) {
            DB::table('RGF_DADOS')->updateOrInsert(
                ['RGF_ANO' => (int) now()->format('Y'), 'RGF_QUADRIMESTRE' => 1],
                [
                    'RGF_RCL' => 98000000,
                    'RGF_DESP_PESSOAL_TOTAL' => 50500000,
                    'RGF_DESP_PESSOAL_LIQUIDA' => 48900000,
                    'RGF_LIMITE_PRUDENCIAL' => 50300000,
                    'RGF_LIMITE_LEGAL' => 52920000,
                    'RGF_DIVIDA_CONSOLIDADA' => 11200000,
                    'RGF_GARANTIAS' => 240000,
                    'RGF_OPERACOES_CREDITO' => 1200000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        if (Schema::hasTable('RREO_DADOS')) {
            DB::table('RREO_DADOS')->updateOrInsert(
                ['RREO_ANO' => (int) now()->format('Y'), 'RREO_BIMESTRE' => 2],
                [
                    'RREO_RECEITA_PREVISTA' => 140000000,
                    'RREO_RECEITA_ARRECADADA' => 52200000,
                    'RREO_DESP_DOTACAO_INICIAL' => 138000000,
                    'RREO_DESP_DOTACAO_ATUALIZADA' => 141000000,
                    'RREO_DESP_EMPENHADA' => 60500000,
                    'RREO_DESP_LIQUIDADA' => 48200000,
                    'RREO_DESP_PAGA' => 44800000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        if (Schema::hasTable('SAGRES_EXPORTACAO')) {
            DB::table('SAGRES_EXPORTACAO')->updateOrInsert(
                ['COMPETENCIA' => now()->format('Y-m')],
                [
                    'ARQUIVO_XML_PATH' => '/exports/sagres_' . now()->format('Ym') . '.xml',
                    'STATUS' => 'VALIDADO',
                    'VALIDACAO_ERROS' => null,
                    'ENVIADO_EM' => now()->subHours(2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
        if (Schema::hasTable('TRANSPARENCIA_EXPORTACAO')) {
            DB::table('TRANSPARENCIA_EXPORTACAO')->updateOrInsert(
                ['TIPO' => 'FOLHA_CSV', 'COMPETENCIA' => now()->format('Y-m')],
                [
                    'ARQUIVO_PATH' => '/exports/transparencia_folha_' . now()->format('Ym') . '.csv',
                    'PUBLICADO_EM' => now()->subHour(),
                    'STATUS' => 'PUBLICADO',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedComunicacaoERotinaPorPerfil(int $adminId): void
    {
        $usuarios = DB::table('USUARIO as u')
            ->join('USUARIO_PERFIL as up', 'up.USUARIO_ID', '=', 'u.USUARIO_ID')
            ->join('PERFIL as p', 'p.PERFIL_ID', '=', 'up.PERFIL_ID')
            ->where('u.USUARIO_ATIVO', 1)
            ->where('up.USUARIO_PERFIL_ATIVO', 1)
            ->orderBy('u.USUARIO_ID')
            ->limit(24)
            ->get([
                'u.USUARIO_ID as usuario_id',
                'u.USUARIO_NOME as nome',
                'p.PERFIL_NOME as perfil_nome',
            ]);
        if ($usuarios->isEmpty()) {
            return;
        }

        if (Schema::hasTable('AGENDA')) {
            $temTabelaAgendaNova = Schema::hasColumn('AGENDA', 'AGENDA_TITULO');
            foreach ($usuarios->take(10) as $idx => $u) {
                $funcId = Schema::hasTable('FUNCIONARIO')
                    ? DB::table('FUNCIONARIO')->where('USUARIO_ID', $u->usuario_id)->value('FUNCIONARIO_ID')
                    : null;
                $setorId = null;
                if ($funcId && Schema::hasTable('LOTACAO')) {
                    $setorId = DB::table('LOTACAO')
                        ->where('FUNCIONARIO_ID', $funcId)
                        ->whereNull('LOTACAO_DATA_FIM')
                        ->value('SETOR_ID');
                }

                if ($temTabelaAgendaNova) {
                    DB::table('AGENDA')->updateOrInsert(
                        [
                            'AGENDA_TITULO' => 'Ritual diário — ' . ($u->perfil_nome ?? 'Perfil'),
                            'AGENDA_DATA' => now()->addDays($idx % 5)->toDateString(),
                            'AGENDA_HORA' => '08:30:00',
                        ],
                        [
                            'FUNCIONARIO_ID' => $funcId,
                            'AGENDA_TIPO' => 'rotina',
                            'AGENDA_LOCAL' => 'Painel GENTE',
                            'AGENDA_DESC' => 'Seed: rotina operacional por perfil/aba.',
                            'AGENDA_ESCOPO' => $idx % 3 === 0 ? 'setor' : 'pessoal',
                            'AGENDA_SETOR_ID' => $setorId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                } else {
                    DB::table('AGENDA')->updateOrInsert(
                        [
                            'EVENTO_TITULO' => 'Ritual diário — ' . ($u->perfil_nome ?? 'Perfil'),
                            'EVENTO_DATA' => now()->addDays($idx % 5)->toDateString(),
                        ],
                        [
                            'EVENTO_HORA_INICIO' => '08:30:00',
                            'EVENTO_HORA_FIM' => '09:00:00',
                            'EVENTO_TIPO' => 'rotina',
                            'EVENTO_DESCRICAO' => 'Seed: rotina operacional por perfil/aba.',
                            'EVENTO_LOCAL' => 'Painel GENTE',
                            'USUARIO_ID' => $u->usuario_id,
                            'SETOR_ID' => $setorId,
                            'EVENTO_PUBLICO' => 0,
                        ]
                    );
                }
            }
        }

        if (Schema::hasTable('DECLARACAO_MODELO')) {
            DB::table('DECLARACAO_MODELO')->updateOrInsert(
                ['MODELO_TIPO' => 'DECLARACAO_VINCULO_FUNCIONAL'],
                [
                    'MODELO_HTML' => '<h1>Declaração de Vínculo</h1><p>Servidor(a): {{nome}}</p><p>Matrícula: {{matricula}}</p><p>Setor: {{setor}}</p>',
                    'MODELO_ATUALIZADO_EM' => now(),
                ]
            );
            DB::table('DECLARACAO_MODELO')->updateOrInsert(
                ['MODELO_TIPO' => 'DECLARACAO_LOTACAO_ATUAL'],
                [
                    'MODELO_HTML' => '<h1>Declaração de Lotação</h1><p>Servidor(a): {{nome}}</p><p>Lotação atual: {{setor}}</p><p>Data: {{data}}</p>',
                    'MODELO_ATUALIZADO_EM' => now(),
                ]
            );
        }

        if (Schema::hasTable('NOTIFICACAO')) {
            foreach ($usuarios as $idx => $u) {
                $perfil = mb_strtolower((string) $u->perfil_nome);
                $url = '/dashboard';
                $titulo = 'Painel diário';
                $tipo = 'info';
                if (str_contains($perfil, 'gest')) {
                    $url = '/portal-gestor';
                    $titulo = 'Ações pendentes da equipe';
                    $tipo = 'warning';
                } elseif (str_contains($perfil, 'rh')) {
                    $url = '/funcionarios';
                    $titulo = 'Fila RH e ajustes cadastrais';
                    $tipo = 'info';
                } elseif (str_contains($perfil, 'admin') || str_contains($perfil, 'desenvolvedor')) {
                    $url = '/dashboard';
                    $titulo = 'Governança geral do sistema';
                    $tipo = 'success';
                } else {
                    $url = '/ponto';
                    $titulo = 'Pendências da minha rotina';
                }

                $existe = DB::table('NOTIFICACAO')
                    ->where('USUARIO_ID', $u->usuario_id)
                    ->where('NOTIFICACAO_TITULO', $titulo)
                    ->where('NOTIFICACAO_URL', $url)
                    ->exists();
                if ($existe) {
                    continue;
                }
                DB::table('NOTIFICACAO')->insert([
                    'USUARIO_ID' => $u->usuario_id,
                    'NOTIFICACAO_TITULO' => $titulo,
                    'NOTIFICACAO_BODY' => 'Seed de rotina por perfil para reforçar fluxo entre abas.',
                    'NOTIFICACAO_TIPO' => $tipo,
                    'NOTIFICACAO_ICONE' => $idx % 2 === 0 ? '🧭' : '📌',
                    'NOTIFICACAO_URL' => $url,
                    'NOTIFICACAO_LIDA' => $idx % 3 === 0 ? 1 : 0,
                    'NOTIFICACAO_DT_CRIACAO' => now()->subHours($idx % 6),
                ]);
            }
        }

        if (Schema::hasTable('COMUNICADO') && Schema::hasTable('COMUNICADO_LEITURA')) {
            $comunicados = DB::table('COMUNICADO')->orderByDesc('COMUNICADO_ID')->limit(3)->get(['COMUNICADO_ID']);
            foreach ($usuarios->take(12) as $u) {
                foreach ($comunicados as $c) {
                    DB::table('COMUNICADO_LEITURA')->updateOrInsert(
                        ['COMUNICADO_ID' => $c->COMUNICADO_ID, 'USUARIO_ID' => $u->usuario_id],
                        ['LEITURA_DT' => now()->subHours(rand(1, 48))]
                    );
                }
            }
        }
    }

    private function seedFinanceiroFolhaConectado(int $adminId): void
    {
        $funcionarios = DB::table('FUNCIONARIO')
            ->when(Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM'), fn($q) => $q->whereNull('FUNCIONARIO_DATA_FIM'))
            ->orderBy('FUNCIONARIO_ID')
            ->limit(10)
            ->pluck('FUNCIONARIO_ID')
            ->values();
        if ($funcionarios->isEmpty()) {
            return;
        }

        if (Schema::hasTable('CONSIG_CONVENIO') && Schema::hasTable('CONSIG_CONTRATO') && Schema::hasTable('CONSIG_PARCELA')) {
            $convenioId = DB::table('CONSIG_CONVENIO')->where('CONVENIO_NOME', 'Banco do Brasil')->value('CONVENIO_ID')
                ?: DB::table('CONSIG_CONVENIO')->value('CONVENIO_ID');
            if ($convenioId) {
                foreach ($funcionarios->take(4) as $idx => $fid) {
                    $contratoId = DB::table('CONSIG_CONTRATO')
                        ->where('FUNCIONARIO_ID', $fid)
                        ->where('NUMERO_CONTRATO', 'CONSIG-SEED-' . $fid)
                        ->value('CONTRATO_ID');
                    if (!$contratoId) {
                        $contratoId = DB::table('CONSIG_CONTRATO')->insertGetId([
                            'FUNCIONARIO_ID' => $fid,
                            'CONVENIO_ID' => $convenioId,
                            'NUMERO_CONTRATO' => 'CONSIG-SEED-' . $fid,
                            'DATA_INICIO' => now()->subMonths(5)->toDateString(),
                            'DATA_FIM' => now()->addMonths(19)->toDateString(),
                            'VALOR_TOTAL' => 12000 + ($idx * 2200),
                            'VALOR_PARCELA' => 480 + ($idx * 70),
                            'PRAZO_MESES' => 24,
                            'PARCELAS_PAGAS' => 5 + $idx,
                            'SALDO_DEVEDOR' => 8400 + ($idx * 1200),
                            'TAXA_JUROS' => 1.85,
                            'STATUS' => 'ATIVO',
                            'OBSERVACAO' => 'Seed integrado com folha e holerite',
                            'CADASTRADO_POR' => $adminId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    DB::table('CONSIG_PARCELA')->updateOrInsert(
                        ['CONTRATO_ID' => $contratoId, 'COMPETENCIA' => now()->format('Y-m'), 'NUMERO_PARCELA' => 6 + $idx],
                        ['VALOR_PARCELA' => 480 + ($idx * 70), 'VALOR_PAGO' => 480 + ($idx * 70), 'STATUS' => 'DESCONTADA', 'created_at' => now(), 'updated_at' => now()]
                    );
                }
            }
        }

        if (Schema::hasTable('RPPS_CONTRIBUICAO')) {
            foreach ($funcionarios->take(4) as $idx => $fid) {
                $base = 4500 + ($idx * 650);
                DB::table('RPPS_CONTRIBUICAO')->updateOrInsert(
                    ['FUNCIONARIO_ID' => $fid, 'COMPETENCIA' => now()->format('Y-m')],
                    [
                        'BASE_CALCULO' => $base,
                        'ALIQUOTA_SERVIDOR' => 0.14,
                        'VALOR_SERVIDOR' => round($base * 0.14, 2),
                        'ALIQUOTA_PATRONAL' => 0.28,
                        'VALOR_PATRONAL' => round($base * 0.28, 2),
                        'STATUS' => $idx % 2 === 0 ? 'PENDENTE' : 'PAGO',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    private function seedComunicados(int $adminId): void
    {
        if (!Schema::hasTable('COMUNICADO')) {
            return;
        }

        $base = [
            [
                'TITULO' => 'Plano de contingência de plantões',
                'CONTEUDO' => 'Escalas com maior risco devem ser revisadas até o fechamento da competência.',
                'CATEGORIA' => 'gestao',
                'PRIORIDADE' => 'alta',
                'FIXADO' => 1,
            ],
            [
                'TITULO' => 'Atualização de rotina do Banco de Horas',
                'CONTEUDO' => 'Todos os gestores devem validar pendências de reconciliação diariamente.',
                'CATEGORIA' => 'rh',
                'PRIORIDADE' => 'normal',
                'FIXADO' => 0,
            ],
        ];

        foreach ($base as $item) {
            $existe = DB::table('COMUNICADO')->where('TITULO', $item['TITULO'])->exists();
            if ($existe) {
                continue;
            }
            DB::table('COMUNICADO')->insert([
                ...$item,
                'ATIVO' => 1,
                'USUARIO_ID' => $adminId,
                'AUTOR_NOME' => 'Admin Sistema',
                'AUTOR_SETOR' => 'Gabinete',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(1),
            ]);
        }
    }

    private function seedOuvidoria(int $adminId): void
    {
        if (!Schema::hasTable('OUVIDORIA')) {
            return;
        }

        $funcId = Schema::hasTable('FUNCIONARIO')
            ? (DB::table('FUNCIONARIO')->value('FUNCIONARIO_ID') ?: null)
            : null;

        $itens = [
            ['tipo' => 'reclamacao', 'urgencia' => 'alta', 'status' => 'em_analise'],
            ['tipo' => 'sugestao', 'urgencia' => 'normal', 'status' => 'respondida'],
            ['tipo' => 'elogio', 'urgencia' => 'normal', 'status' => 'recebida'],
        ];

        foreach ($itens as $idx => $i) {
            $protocolo = 'OUV-' . now()->format('ym') . '-' . str_pad((string) ($idx + 1), 4, '0', STR_PAD_LEFT);
            if (DB::table('OUVIDORIA')->where('OUVIDORIA_PROTOCOLO', $protocolo)->exists()) {
                continue;
            }
            DB::table('OUVIDORIA')->insert([
                'FUNCIONARIO_ID' => $funcId,
                'OUVIDORIA_TIPO' => $i['tipo'],
                'OUVIDORIA_AREA' => 'Ponto Eletrônico',
                'OUVIDORIA_URGENCIA' => $i['urgencia'],
                'OUVIDORIA_DESC' => 'Registro seed fase 2 para validar fluxos da ouvidoria administrativa.',
                'OUVIDORIA_STATUS' => $i['status'],
                'OUVIDORIA_PROTOCOLO' => $protocolo,
                'OUVIDORIA_DATA' => now()->subDays($idx + 1)->toDateString(),
                'OUVIDORIA_ANONIMO' => 0,
                'OUVIDORIA_RESPOSTA' => $i['status'] === 'respondida' ? 'Resposta de acompanhamento registrada pela gestão.' : null,
                'created_at' => now()->subDays($idx + 1),
                'updated_at' => now()->subDays($idx),
            ]);
        }
    }

    private function seedPesquisasSatisfacao(int $adminId): void
    {
        if (!Schema::hasTable('PESQUISA_SATISFACAO') || !Schema::hasTable('PESQUISA_PERGUNTA')) {
            return;
        }

        $pesquisa = DB::table('PESQUISA_SATISFACAO')
            ->where('PESQUISA_TITULO', 'Pulso Operacional — Banco de Horas')
            ->first();

        if (!$pesquisa) {
            $pesquisaId = DB::table('PESQUISA_SATISFACAO')->insertGetId([
                'PESQUISA_TITULO' => 'Pulso Operacional — Banco de Horas',
                'PESQUISA_DESC' => 'Pesquisa seed para testar dashboards e respostas por perfil.',
                'PESQUISA_STATUS' => 'aberta',
                'PESQUISA_INICIO' => now()->toDateString(),
                'PESQUISA_FIM' => now()->addDays(15)->toDateString(),
                'CRIADO_POR' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $pesquisaId = (int) $pesquisa->PESQUISA_ID;
        }

        $perguntas = [
            ['PERGUNTA_TEXTO' => 'Quão clara está a visão de déficit por setor?', 'PERGUNTA_TIPO' => 'nps', 'PERGUNTA_ORDEM' => 1],
            ['PERGUNTA_TEXTO' => 'Os modais operacionais ajudam na decisão diária?', 'PERGUNTA_TIPO' => 'estrelas', 'PERGUNTA_ORDEM' => 2],
        ];

        foreach ($perguntas as $p) {
            $existe = DB::table('PESQUISA_PERGUNTA')
                ->where('PESQUISA_ID', $pesquisaId)
                ->where('PERGUNTA_TEXTO', $p['PERGUNTA_TEXTO'])
                ->exists();
            if ($existe) {
                continue;
            }
            DB::table('PESQUISA_PERGUNTA')->insert([
                'PESQUISA_ID' => $pesquisaId,
                ...$p,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedComprasContratos(int $adminId): void
    {
        if (Schema::hasTable('PROCESSO_LICITATORIO')) {
            $existe = DB::table('PROCESSO_LICITATORIO')->where('PROCESSO_NUMERO', '2026/001')->exists();
            if (!$existe) {
                DB::table('PROCESSO_LICITATORIO')->insert([
                    'PROCESSO_NUMERO' => '2026/001',
                    'PROCESSO_MODALIDADE' => 'PREGAO',
                    'PROCESSO_OBJETO' => 'Aquisição de insumos hospitalares para cobertura trimestral.',
                    'PROCESSO_VALOR_ESTIMADO' => 380000.00,
                    'PROCESSO_STATUS' => 'EM_ANDAMENTO',
                    'PROCESSO_DATA_ABERTURA' => now()->subDays(20)->toDateString(),
                    'UO_ID' => 1,
                    'USUARIO_ID' => $adminId,
                    'created_at' => now()->subDays(20),
                    'updated_at' => now()->subDays(2),
                ]);
            }
        }

        if (Schema::hasTable('PEDIDO_COMPRA')) {
            $existe = DB::table('PEDIDO_COMPRA')->where('PEDIDO_DESCRICAO', 'like', 'Reposição crítica%')->exists();
            if (!$existe) {
                DB::table('PEDIDO_COMPRA')->insert([
                    'UO_ID' => 1,
                    'PEDIDO_DESCRICAO' => 'Reposição crítica de materiais de UTI e emergência.',
                    'PEDIDO_VALOR_ESTIMADO' => 124500.00,
                    'PEDIDO_STATUS' => 'EM_ANALISE',
                    'SOLICITANTE_ID' => $adminId,
                    'created_at' => now()->subDays(8),
                    'updated_at' => now()->subDays(1),
                ]);
            }
        }
    }

    private function seedAlmoxarifado(int $adminId): void
    {
        if (!Schema::hasTable('ALMOXARIFADO') || !Schema::hasTable('ITEM_ESTOQUE')) {
            return;
        }

        $almoxId = DB::table('ALMOXARIFADO')->where('ALMOX_NOME', 'Almoxarifado Central Saúde')->value('ALMOX_ID');
        if (!$almoxId) {
            $almoxId = DB::table('ALMOXARIFADO')->insertGetId([
                'ALMOX_NOME' => 'Almoxarifado Central Saúde',
                'UO_ID' => 1,
                'ALMOX_RESPONSAVEL' => 'Coordenação Logística',
                'ALMOX_ATIVO' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $itemId = DB::table('ITEM_ESTOQUE')->where('ITEM_CODIGO', 'MED-UTI-001')->value('ITEM_ID');
        if (!$itemId) {
            $itemId = DB::table('ITEM_ESTOQUE')->insertGetId([
                'ITEM_CODIGO' => 'MED-UTI-001',
                'ITEM_DESCRICAO' => 'Equipo macrogotas estéril',
                'ITEM_UNIDADE' => 'UN',
                'ITEM_CATEGORIA' => 'MATERIAL',
                'ITEM_ESTOQUE_MINIMO' => 200,
                'ITEM_ATIVO' => 1,
                'ITEM_REQUER_LOTE' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('SALDO_ESTOQUE')) {
            DB::table('SALDO_ESTOQUE')->updateOrInsert(
                ['ALMOX_ID' => $almoxId, 'ITEM_ID' => $itemId],
                ['SALDO_QUANTIDADE' => 120, 'SALDO_VALOR_MEDIO' => 5.80, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        if (Schema::hasTable('MOVIMENTACAO_ESTOQUE')) {
            $movExiste = DB::table('MOVIMENTACAO_ESTOQUE')
                ->where('ALMOX_ID', $almoxId)
                ->where('ITEM_ID', $itemId)
                ->where('MOV_DOCUMENTO', 'NF-SEED-2604')
                ->exists();
            if (!$movExiste) {
                DB::table('MOVIMENTACAO_ESTOQUE')->insert([
                    'ALMOX_ID' => $almoxId,
                    'ITEM_ID' => $itemId,
                    'MOV_TIPO' => 'SAIDA',
                    'MOV_QUANTIDADE' => 80,
                    'MOV_VALOR_UNITARIO' => 5.80,
                    'MOV_DOCUMENTO' => 'NF-SEED-2604',
                    'REGISTRADO_POR' => $adminId,
                    'MOV_OBS' => 'Consumo assistencial elevado (seed fase 2).',
                    'created_at' => now()->subDay(),
                    'updated_at' => now()->subDay(),
                ]);
            }
        }
    }

    private function seedModulosIntegrados(int $adminId): void
    {
        $funcionarios = DB::table('FUNCIONARIO')
            ->orderBy('FUNCIONARIO_ID')
            ->limit(8)
            ->pluck('FUNCIONARIO_ID')
            ->values();

        if (Schema::hasTable('CONTRATO_ADMINISTRATIVO')) {
            $processoId = Schema::hasTable('PROCESSO_LICITATORIO')
                ? DB::table('PROCESSO_LICITATORIO')->value('PROCESSO_ID')
                : null;

            $contratos = [
                ['num' => 'CT-ADM-2026/014', 'fornecedor' => 'ServPrime Apoio Hospitalar Ltda', 'objeto' => 'Apoio operacional hospitalar e higienizacao', 'valor' => 285000.00, 'inicio' => now()->subMonths(3)->toDateString(), 'fim' => now()->addMonths(9)->toDateString(), 'status' => 'VIGENTE'],
                ['num' => 'CT-ADM-2026/019', 'fornecedor' => 'MediSupply Nordeste S/A', 'objeto' => 'Fornecimento de insumos de UTI e emergencia', 'valor' => 412500.00, 'inicio' => now()->subMonths(1)->toDateString(), 'fim' => now()->addMonths(11)->toDateString(), 'status' => 'VIGENTE'],
                ['num' => 'CT-ADM-2025/031', 'fornecedor' => 'LifeTech Manutencao Clinica', 'objeto' => 'Manutencao preventiva de equipamentos medico-hospitalares', 'valor' => 198700.00, 'inicio' => now()->subYear()->toDateString(), 'fim' => now()->addDays(24)->toDateString(), 'status' => 'VIGENTE'],
            ];

            foreach ($contratos as $c) {
                $contratoId = DB::table('CONTRATO_ADMINISTRATIVO')->where('CONTRATO_NUMERO', $c['num'])->value('CONTRATO_ID');
                if (!$contratoId) {
                    $contratoId = DB::table('CONTRATO_ADMINISTRATIVO')->insertGetId([
                        'PROCESSO_ID' => $processoId,
                        'CONTRATO_NUMERO' => $c['num'],
                        'CONTRATO_FORNECEDOR' => $c['fornecedor'],
                        'CONTRATO_OBJETO' => $c['objeto'],
                        'CONTRATO_VALOR' => $c['valor'],
                        'CONTRATO_INICIO' => $c['inicio'],
                        'CONTRATO_FIM' => $c['fim'],
                        'CONTRATO_STATUS' => $c['status'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if (Schema::hasTable('CONTRATO_FISCALIZACAO')) {
                    DB::table('CONTRATO_FISCALIZACAO')->updateOrInsert(
                        ['CONTRATO_ID' => $contratoId, 'FISCAL_COMPETENCIA' => now()->format('m/Y')],
                        [
                            'FISCAL_DATA' => now()->subDays(5)->toDateString(),
                            'FISCAL_STATUS' => 'REGULAR',
                            'FISCAL_OBSERVACAO' => 'Seed integrado com Monitor OSS e Compras.',
                            'FISCAL_RESPONSAVEL' => 'Fiscal Contratual Seed',
                            'REGISTRADO_POR' => $adminId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }

        if (Schema::hasTable('BENEFICIO') && Schema::hasTable('FUNCIONARIO_BENEFICIO')) {
            $catalogo = [
                ['nome' => 'Auxilio Alimentacao', 'tipo' => 'VR', 'valor' => 650.00],
                ['nome' => 'Vale Transporte', 'tipo' => 'VT', 'valor' => 280.00],
                ['nome' => 'Auxilio Creche', 'tipo' => 'CRECHE', 'valor' => 320.00],
            ];
            $beneficiosIds = [];
            foreach ($catalogo as $b) {
                DB::table('BENEFICIO')->updateOrInsert(
                    ['BENEFICIO_NOME' => $b['nome']],
                    [
                        'BENEFICIO_TIPO' => $b['tipo'],
                        'BENEFICIO_VALOR' => $b['valor'],
                        'BENEFICIO_STATUS' => 'ativo',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
                $beneficiosIds[] = (int) DB::table('BENEFICIO')->where('BENEFICIO_NOME', $b['nome'])->value('BENEFICIO_ID');
            }
            foreach ($funcionarios->take(5) as $idx => $fid) {
                DB::table('FUNCIONARIO_BENEFICIO')->updateOrInsert(
                    ['FUNCIONARIO_ID' => $fid, 'BENEFICIO_ID' => $beneficiosIds[$idx % max(count($beneficiosIds), 1)]],
                    [
                        'DATA_INICIO' => now()->subMonths(2)->toDateString(),
                        'DATA_FIM' => null,
                        'VALOR_ESPECIFICO' => null,
                        'DEPENDENTES' => $idx % 3,
                        'STATUS' => 'ativo',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        if (Schema::hasTable('VERBA_TIPO')) {
            $tipos = [
                ['nome' => 'Auxilio Transporte Complementar', 'grupo' => 'MENSAL', 'ir' => 0, 'inss' => 0, 'comp' => 0],
                ['nome' => 'Diaria Operacional', 'grupo' => 'MENSAL', 'ir' => 0, 'inss' => 0, 'comp' => 1],
            ];
            foreach ($tipos as $t) {
                DB::table('VERBA_TIPO')->updateOrInsert(
                    ['VERBA_NOME' => $t['nome']],
                    [
                        'VERBA_GRUPO' => $t['grupo'],
                        'INCIDE_IR' => $t['ir'],
                        'INCIDE_INSS' => $t['inss'],
                        'INCIDE_RPPS' => 0,
                        'REQUER_COMPROVANTE' => $t['comp'],
                        'ATIVO' => 1,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        if (Schema::hasTable('CONSIGNATARIA') && Schema::hasTable('LAYOUT_CONSIGNATARIA') && Schema::hasTable('CONSIG_REMESSA')) {
            $consigId = DB::table('CONSIGNATARIA')->where('CONSIGNATARIA_CODIGO', 'NEO01')->value('CONSIGNATARIA_ID');
            if (!$consigId) {
                $consigId = DB::table('CONSIGNATARIA')->insertGetId([
                    'CONSIGNATARIA_NOME' => 'Neoconsig',
                    'CONSIGNATARIA_CNPJ' => '12345678000190',
                    'CONSIGNATARIA_CODIGO' => 'NEO01',
                    'CONSIGNATARIA_TIPO' => 'banco',
                    'CONSIGNATARIA_ATIVA' => 1,
                    'CONSIGNATARIA_MARGEM_MAX_PCT' => 35,
                    'CONSIGNATARIA_CONTATO' => 'suporte@neoconsig.local',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $layoutId = DB::table('LAYOUT_CONSIGNATARIA')->where('CONSIGNATARIA_ID', $consigId)->value('LAYOUT_ID');
            if (!$layoutId) {
                $layoutCols = Schema::getColumnListing('LAYOUT_CONSIGNATARIA');
                $layoutPayload = [
                    'CONSIGNATARIA_ID' => $consigId,
                    'LAYOUT_TIPO' => 'remessa',
                    'LAYOUT_FORMATO' => 'txt',
                    'LAYOUT_VERSAO' => '1.0',
                    'LAYOUT_ATIVO' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (in_array('LAYOUT_NOME', $layoutCols, true)) {
                    $layoutPayload['LAYOUT_NOME'] = 'NEOCONSIG_FINANCEIRO';
                }
                if (in_array('LAYOUT_DIRECAO', $layoutCols, true)) {
                    $layoutPayload['LAYOUT_DIRECAO'] = 'SAIDA';
                }
                if (in_array('LAYOUT_TAMANHO_LINHA', $layoutCols, true)) {
                    $layoutPayload['LAYOUT_TAMANHO_LINHA'] = 66;
                }
                if (in_array('LAYOUT_ENCODING', $layoutCols, true)) {
                    $layoutPayload['LAYOUT_ENCODING'] = 'UTF-8';
                }
                if (in_array('LAYOUT_MAPEAMENTO', $layoutCols, true)) {
                    $layoutPayload['LAYOUT_MAPEAMENTO'] = json_encode(['matricula' => [1, 10], 'valor' => [11, 22]], JSON_UNESCAPED_UNICODE);
                }
                $layoutId = DB::table('LAYOUT_CONSIGNATARIA')->insertGetId($layoutPayload);
            }
            DB::table('CONSIG_REMESSA')->updateOrInsert(
                ['CONSIGNATARIA_ID' => $consigId, 'REMESSA_COMPETENCIA' => now()->format('Ym'), 'REMESSA_TIPO' => 'envio'],
                [
                    'LAYOUT_ID' => $layoutId,
                    'REMESSA_STATUS' => 'gerado',
                    'REMESSA_ARQUIVO_PATH' => 'remessa_neoconsig_' . now()->format('Ym') . '.txt',
                    'REMESSA_TOTAL_REGISTROS' => 6,
                    'REMESSA_TOTAL_VALOR' => 12450.00,
                    'REMESSA_ERROS' => 0,
                    'REMESSA_LOG' => 'Seed OK: 6 registros processados.',
                    'REMESSA_OBS' => 'Seed integrado com modulo consignatarias.',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        if (Schema::hasTable('RESCISAO_CALCULO') && Schema::hasTable('FUNCIONARIO')) {
            $resCols = Schema::getColumnListing('RESCISAO_CALCULO');
            $funcCols = Schema::getColumnListing('FUNCIONARIO');
            $funcResc = DB::table('FUNCIONARIO')
                ->orderByDesc('FUNCIONARIO_ID')
                ->limit(2)
                ->pluck('FUNCIONARIO_ID')
                ->values();

            foreach ($funcResc as $idx => $fid) {
                $dataExon = now()->subDays(20 + ($idx * 5))->toDateString();
                $payload = [
                    'FUNCIONARIO_ID' => $fid,
                    'DATA_EXONERACAO' => $dataExon,
                    'MOTIVO_SAIDA' => $idx === 0 ? 'EXONERACAO' : 'APOSENTADORIA',
                    'PORTARIA_NUM' => 'PT-RES-SEED-2026-' . str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                    'DATA_CALCULO' => now()->subDays(18 + ($idx * 5)),
                    'CALCULADO_POR' => $adminId,
                    'STATUS' => 'VALIDADO',
                    'SALDO_SALARIO' => 1350 + ($idx * 220),
                    'FERIAS_PROP' => 980 + ($idx * 180),
                    'FERIAS_PROP_TERCIO' => 326 + ($idx * 60),
                    'FERIAS_VENCIDAS' => 0,
                    'FERIAS_VENCIDAS_TERCIO' => 0,
                    'DECIMO_TERCEIRO_PROP' => 1220 + ($idx * 210),
                    'LICENCA_PREMIO' => 0,
                    'FGTS_MULTA' => $idx === 0 ? 1480 : 0,
                    'TOTAL_BRUTO' => 3876 + ($idx * 670),
                    'DESCONTO_IRRF' => 148 + ($idx * 25),
                    'TOTAL_LIQUIDO' => 3728 + ($idx * 645),
                    'REGIME_PREV' => $idx === 0 ? 'RGPS' : 'RPPS',
                    'updated_at' => now(),
                    'created_at' => now(),
                ];

                if (in_array('FOLHA_ID', $resCols, true)) {
                    $payload['FOLHA_ID'] = null;
                }
                if (in_array('OBSERVACOES', $resCols, true)) {
                    $payload['OBSERVACOES'] = 'Seed dedicado de exoneração para fila de elegíveis.';
                }

                DB::table('RESCISAO_CALCULO')->updateOrInsert(
                    ['FUNCIONARIO_ID' => $fid, 'DATA_EXONERACAO' => $dataExon],
                    $payload
                );

                $funcUpdate = [];
                if (in_array('updated_at', $funcCols, true)) {
                    $funcUpdate['updated_at'] = now();
                }
                if (in_array('FUNCIONARIO_DATA_FIM', $funcCols, true)) {
                    $funcUpdate['FUNCIONARIO_DATA_FIM'] = $dataExon;
                }
                if (in_array('FUNCIONARIO_MOTIVO_SAIDA', $funcCols, true)) {
                    $funcUpdate['FUNCIONARIO_MOTIVO_SAIDA'] = $idx === 0 ? 'EXONERACAO' : 'APOSENTADORIA';
                }
                if (in_array('FUNCIONARIO_DATA_EXONERACAO', $funcCols, true)) {
                    $funcUpdate['FUNCIONARIO_DATA_EXONERACAO'] = $dataExon;
                }
                if (in_array('FUNCIONARIO_PORTARIA_SAIDA', $funcCols, true)) {
                    $funcUpdate['FUNCIONARIO_PORTARIA_SAIDA'] = 'PT-RES-SEED-2026-' . str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
                }
                if (in_array('FUNCIONARIO_STATUS_RESCISORIO', $funcCols, true)) {
                    $funcUpdate['FUNCIONARIO_STATUS_RESCISORIO'] = 'PENDENTE';
                }
                if (!empty($funcUpdate)) {
                    DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $fid)->update($funcUpdate);
                }
            }

            // Reconciliação global: toda rescisão validada precisa refletir no vínculo funcional.
            $pendencias = DB::table('RESCISAO_CALCULO as r')
                ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'r.FUNCIONARIO_ID')
                ->whereIn('r.STATUS', ['VALIDADO', 'CALCULADO', 'INCLUIDO_FOLHA'])
                ->whereNotNull('r.DATA_EXONERACAO')
                ->where(function ($q) {
                    $q->whereNull('f.FUNCIONARIO_DATA_FIM')
                      ->orWhereRaw('CONVERT(date, f.FUNCIONARIO_DATA_FIM) <> CONVERT(date, r.DATA_EXONERACAO)');
                })
                ->select('r.FUNCIONARIO_ID', 'r.DATA_EXONERACAO', 'r.MOTIVO_SAIDA', 'r.PORTARIA_NUM')
                ->get();

            foreach ($pendencias as $p) {
                $fix = [];
                if (in_array('FUNCIONARIO_DATA_FIM', $funcCols, true)) {
                    $fix['FUNCIONARIO_DATA_FIM'] = $p->DATA_EXONERACAO;
                }
                if (in_array('FUNCIONARIO_MOTIVO_SAIDA', $funcCols, true)) {
                    $fix['FUNCIONARIO_MOTIVO_SAIDA'] = $p->MOTIVO_SAIDA;
                }
                if (in_array('FUNCIONARIO_DATA_EXONERACAO', $funcCols, true)) {
                    $fix['FUNCIONARIO_DATA_EXONERACAO'] = $p->DATA_EXONERACAO;
                }
                if (in_array('FUNCIONARIO_PORTARIA_SAIDA', $funcCols, true)) {
                    $fix['FUNCIONARIO_PORTARIA_SAIDA'] = $p->PORTARIA_NUM;
                }
                if (in_array('FUNCIONARIO_STATUS_RESCISORIO', $funcCols, true)) {
                    $fix['FUNCIONARIO_STATUS_RESCISORIO'] = 'PENDENTE';
                }
                if (in_array('updated_at', $funcCols, true)) {
                    $fix['updated_at'] = now();
                }
                if (!empty($fix)) {
                    DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $p->FUNCIONARIO_ID)->update($fix);
                }
            }
        }
    }

    private function seedSobreavisoEAcionamentos(int $adminId): void
    {
        if (!Schema::hasTable('FUNCIONARIO')) {
            return;
        }

        $funcionarios = DB::table('FUNCIONARIO')
            ->when(Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM'), fn($q) => $q->whereNull('FUNCIONARIO_DATA_FIM'))
            ->orderBy('FUNCIONARIO_ID')
            ->limit(8)
            ->pluck('FUNCIONARIO_ID')
            ->values();
        if ($funcionarios->isEmpty()) {
            return;
        }

        $setores = Schema::hasTable('SETOR')
            ? DB::table('SETOR')->orderBy('SETOR_ID')->limit(8)->pluck('SETOR_ID')->values()
            : collect();

        $inicioCompetencia = now()->startOfMonth();
        $periodos = [
            [$inicioCompetencia->copy()->addDays(1),  $inicioCompetencia->copy()->addDays(2)],
            [$inicioCompetencia->copy()->addDays(8),  $inicioCompetencia->copy()->addDays(9)],
            [$inicioCompetencia->copy()->addDays(15), $inicioCompetencia->copy()->addDays(16)],
            [$inicioCompetencia->copy()->addDays(22), $inicioCompetencia->copy()->addDays(23)],
        ];

        // Cobertura para tabela legada/flexível SOBRAVISO (intervalos).
        if (Schema::hasTable('SOBREAVISO')) {
            $cols = Schema::getColumnListing('SOBREAVISO');
            foreach ($funcionarios as $idx => $funcId) {
                [$ini, $fim] = $periodos[$idx % count($periodos)];
                $setorId = $setores->isNotEmpty() ? (int) $setores[$idx % $setores->count()] : null;

                $payload = array_filter([
                    'FUNCIONARIO_ID' => in_array('FUNCIONARIO_ID', $cols, true) ? (int) $funcId : null,
                    'SETOR_ID' => in_array('SETOR_ID', $cols, true) ? $setorId : null,
                    'SOBREAVISO_INICIO' => in_array('SOBREAVISO_INICIO', $cols, true) ? $ini->toDateString() : null,
                    'SOBREAVISO_FIM' => in_array('SOBREAVISO_FIM', $cols, true) ? $fim->toDateString() : null,
                    'SOBREAVISO_DATA' => in_array('SOBREAVISO_DATA', $cols, true) ? $ini->toDateString() : null,
                    'SOBREAVISO_TURNO' => in_array('SOBREAVISO_TURNO', $cols, true) ? ($idx % 2 === 0 ? 'Noturno' : 'Diurno') : null,
                    'SOBREAVISO_HORAS' => in_array('SOBREAVISO_HORAS', $cols, true) ? 12 : null,
                    'SOBREAVISO_SETOR' => in_array('SOBREAVISO_SETOR', $cols, true) ? 'Setor Assistencial' : null,
                    'SOBREAVISO_PERCENTUAL' => in_array('SOBREAVISO_PERCENTUAL', $cols, true) ? 30 : null,
                    'SOBREAVISO_VALOR' => in_array('SOBREAVISO_VALOR', $cols, true) ? 320.0 + ($idx * 12) : null,
                    'SOBREAVISO_ATIVO' => in_array('SOBREAVISO_ATIVO', $cols, true) ? ($fim->gte(now()) ? 1 : 0) : null,
                    'USUARIO_ID' => in_array('USUARIO_ID', $cols, true) ? $adminId : null,
                    'created_at' => in_array('created_at', $cols, true) ? now() : null,
                    'updated_at' => in_array('updated_at', $cols, true) ? now() : null,
                ], fn($v) => $v !== null);

                if (empty($payload)) {
                    continue;
                }

                DB::table('SOBREAVISO')->updateOrInsert(
                    [
                        'FUNCIONARIO_ID' => (int) $funcId,
                        'SOBREAVISO_INICIO' => $ini->toDateString(),
                    ],
                    $payload
                );
            }
        }

        // Cobertura para ESCALA_SOBREAVISO (por dia).
        if (Schema::hasTable('ESCALA_SOBREAVISO')) {
            $cols = Schema::getColumnListing('ESCALA_SOBREAVISO');
            foreach ($funcionarios as $idx => $funcId) {
                [$ini] = $periodos[$idx % count($periodos)];
                $setorId = $setores->isNotEmpty() ? (int) $setores[$idx % $setores->count()] : null;
                $datas = [$ini->copy(), $ini->copy()->addDay()];
                foreach ($datas as $dataSob) {
                    $payload = array_filter([
                        'FUNCIONARIO_ID' => in_array('FUNCIONARIO_ID', $cols, true) ? (int) $funcId : null,
                        'SETOR_ID' => in_array('SETOR_ID', $cols, true) ? $setorId : null,
                        'SOBREAVISO_DATA' => in_array('SOBREAVISO_DATA', $cols, true) ? $dataSob->toDateString() : null,
                        'SOBREAVISO_TURNO' => in_array('SOBREAVISO_TURNO', $cols, true) ? ($idx % 2 === 0 ? 'Noturno' : 'Diurno') : null,
                        'SOBREAVISO_HORAS' => in_array('SOBREAVISO_HORAS', $cols, true) ? 12 : null,
                        'created_at' => in_array('created_at', $cols, true) ? now() : null,
                        'updated_at' => in_array('updated_at', $cols, true) ? now() : null,
                    ], fn($v) => $v !== null);

                    if (empty($payload)) {
                        continue;
                    }

                    DB::table('ESCALA_SOBREAVISO')->updateOrInsert(
                        [
                            'FUNCIONARIO_ID' => (int) $funcId,
                            'SOBREAVISO_DATA' => $dataSob->toDateString(),
                        ],
                        $payload
                    );
                }
            }
        }

        // Cobertura acionamentos (legado/novo).
        $datasAcion = [
            $inicioCompetencia->copy()->addDays(2),
            $inicioCompetencia->copy()->addDays(10),
            $inicioCompetencia->copy()->addDays(18),
        ];

        if (Schema::hasTable('ACIONAMENTO')) {
            $cols = Schema::getColumnListing('ACIONAMENTO');
            foreach ($funcionarios->take(5) as $idx => $funcId) {
                $dia = $datasAcion[$idx % count($datasAcion)];
                $horaIni = $idx % 2 === 0 ? '20:00' : '07:00';
                $horaFim = $idx % 2 === 0 ? '23:00' : '10:00';
                $payload = array_filter([
                    'FUNCIONARIO_ID' => in_array('FUNCIONARIO_ID', $cols, true) ? (int) $funcId : null,
                    'ACIONAMENTO_DATA' => in_array('ACIONAMENTO_DATA', $cols, true) ? $dia->toDateString() : null,
                    'ACIONAMENTO_HORA_INI' => in_array('ACIONAMENTO_HORA_INI', $cols, true) ? $horaIni : null,
                    'ACIONAMENTO_HORA_FIM' => in_array('ACIONAMENTO_HORA_FIM', $cols, true) ? $horaFim : null,
                    'ACIONAMENTO_LOCAL' => in_array('ACIONAMENTO_LOCAL', $cols, true) ? 'Unidade de Internação' : null,
                    'ACIONAMENTO_MOTIVO' => in_array('ACIONAMENTO_MOTIVO', $cols, true) ? 'Cobertura assistencial de urgência' : null,
                    'ACIONAMENTO_DURACAO' => in_array('ACIONAMENTO_DURACAO', $cols, true) ? 3 : null,
                    'ACIONAMENTO_VALOR' => in_array('ACIONAMENTO_VALOR', $cols, true) ? 222.00 : null,
                    'ACIONAMENTO_PAGO' => in_array('ACIONAMENTO_PAGO', $cols, true) ? ($idx % 3 === 0 ? 1 : 0) : null,
                    'created_at' => in_array('created_at', $cols, true) ? now() : null,
                    'updated_at' => in_array('updated_at', $cols, true) ? now() : null,
                ], fn($v) => $v !== null);

                if (empty($payload)) {
                    continue;
                }

                DB::table('ACIONAMENTO')->updateOrInsert(
                    [
                        'FUNCIONARIO_ID' => (int) $funcId,
                        'ACIONAMENTO_DATA' => $dia->toDateString(),
                    ],
                    $payload
                );
            }
        } elseif (Schema::hasTable('ACIONAMENTO_SOBREAVISO')) {
            $cols = Schema::getColumnListing('ACIONAMENTO_SOBREAVISO');
            foreach ($funcionarios->take(5) as $idx => $funcId) {
                $dia = $datasAcion[$idx % count($datasAcion)];
                $payload = array_filter([
                    'FUNCIONARIO_ID' => in_array('FUNCIONARIO_ID', $cols, true) ? (int) $funcId : null,
                    'ACIONAMENTO_DATA' => in_array('ACIONAMENTO_DATA', $cols, true) ? $dia->toDateString() : null,
                    'ACIONAMENTO_HORA' => in_array('ACIONAMENTO_HORA', $cols, true) ? '20:00-23h' : null,
                    'ACIONAMENTO_MOTIVO' => in_array('ACIONAMENTO_MOTIVO', $cols, true) ? 'Cobertura assistencial de urgência' : null,
                    'ACIONAMENTO_LOCAL' => in_array('ACIONAMENTO_LOCAL', $cols, true) ? 'Unidade de Internação' : null,
                    'ACIONAMENTO_DURACAO' => in_array('ACIONAMENTO_DURACAO', $cols, true) ? 3 : null,
                    'ACIONAMENTO_VALOR' => in_array('ACIONAMENTO_VALOR', $cols, true) ? 222.00 : null,
                    'ACIONAMENTO_PAGO' => in_array('ACIONAMENTO_PAGO', $cols, true) ? ($idx % 3 === 0 ? 1 : 0) : null,
                    'created_at' => in_array('created_at', $cols, true) ? now() : null,
                    'updated_at' => in_array('updated_at', $cols, true) ? now() : null,
                ], fn($v) => $v !== null);

                if (empty($payload)) {
                    continue;
                }

                DB::table('ACIONAMENTO_SOBREAVISO')->updateOrInsert(
                    [
                        'FUNCIONARIO_ID' => (int) $funcId,
                        'ACIONAMENTO_DATA' => $dia->toDateString(),
                    ],
                    $payload
                );
            }
        }
    }
}
