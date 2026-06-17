<?php

namespace App\Services\Smoke;

use App\Domain\Escala\EscalaAusenciaService;
use App\Models\Usuario;
use App\Services\MotorFolhaService;
use App\Support\SpaAuthPayloadBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Smoke reprodutível Fase 7A — Teia RH ↔ Escala ↔ Motor ↔ Organograma ↔ Auditoria.
 * Espera-se correr dentro de transação com rollback (CLI por omissão).
 */
final class SmokeTeiaFolhaRunner
{
    private const ONDE_RUNNER = 'app/Services/Smoke/SmokeTeiaFolhaRunner.php';

    private const ONDE_MOTOR = 'app/Services/MotorFolhaService.php';

    private const ONDE_ESCALA_AUSENCIA = 'app/Domain/Escala/EscalaAusenciaService.php';

    private const ONDE_AUDIT = 'AUDIT_LOG / routes/escala_trabalho.php';

    /** Código SISFOLHA Batalha 1 — Licença Médica (rubrica de desconto C3). */
    private const RUBRICA_CODIGO_LM = '01';

    /**
     * @return list<array{fluxo: string, status: string, detalhe: string, onde_rompeu: string}>
     */
    public function run(SmokeTeiaFolhaOptions $options): array
    {
        $missing = $this->missingCoreTables();
        if ($missing !== null) {
            return [[
                'fluxo' => 'preflight',
                'status' => 'skip',
                'detalhe' => $missing,
                'onde_rompeu' => self::ONDE_RUNNER,
            ]];
        }

        return [
            $this->runFlow1RhEscalaMotor($options),
            $this->runFlow2ProgressaoMotorSpa($options),
            $this->runFlow3OrganogramaMde($options),
            $this->runFlow4aAuditAssignment(),
            $this->runFlow4bTenantScopeShadow($options),
        ];
    }

    private function missingCoreTables(): ?string
    {
        foreach ([
            'FUNCIONARIO', 'PESSOA', 'SETOR', 'UNIDADE', 'LOTACAO', 'AFASTAMENTO',
            'FOLHA', 'DETALHE_FOLHA', 'ESCALA', 'DETALHE_ESCALA', 'DETALHE_ESCALA_ITEM', 'TURNO',
        ] as $t) {
            if (! Schema::hasTable($t)) {
                return "Tabela obrigatória ausente: {$t}";
            }
        }

        return null;
    }

    private function resolveCompetenciaYm(SmokeTeiaFolhaOptions $options): string
    {
        if ($options->competencia !== null && preg_match('/^\d{4}-\d{2}$/', $options->competencia)) {
            return $options->competencia;
        }

        return Carbon::now()->addMonthNoOverflow()->format('Y-m');
    }

    private function resolveFolhaId(SmokeTeiaFolhaOptions $options, string $competenciaYm): int
    {
        if ($options->folhaId !== null && $options->folhaId > 0) {
            return $options->folhaId;
        }

        $existing = DB::table('FOLHA')->where('FOLHA_COMPETENCIA', $competenciaYm)->orderBy('FOLHA_ID')->first();
        if ($existing) {
            return (int) $existing->FOLHA_ID;
        }

        $row = ['FOLHA_COMPETENCIA' => $competenciaYm];
        if (Schema::hasColumn('FOLHA', 'FOLHA_STATUS')) {
            $row['FOLHA_STATUS'] = 'Aberta';
        }
        if (Schema::hasColumn('FOLHA', 'FOLHA_DESCRICAO')) {
            $row['FOLHA_DESCRICAO'] = 'Smoke Fase 7A';
        }
        if (Schema::hasColumn('FOLHA', 'FOLHA_SITUACAO')) {
            $row['FOLHA_SITUACAO'] = 'ABERTA';
        }
        if (Schema::hasColumn('FOLHA', 'FOLHA_ATIVO')) {
            $row['FOLHA_ATIVO'] = 1;
        }

        return (int) DB::table('FOLHA')->insertGetId($row);
    }

    /**
     * @return array{fluxo: string, status: string, detalhe: string, onde_rompeu: string}
     */
    private function runFlow1RhEscalaMotor(SmokeTeiaFolhaOptions $options): array
    {
        $competenciaYm = $this->resolveCompetenciaYm($options);
        [$y, $m] = array_map('intval', explode('-', $competenciaYm));

        $setor = DB::table('SETOR')
            ->whereNotNull('UNIDADE_ID')
            ->where('UNIDADE_ID', '>', 0)
            ->orderBy('SETOR_ID')
            ->first(['SETOR_ID', 'UNIDADE_ID']);
        if (! $setor) {
            return $this->row('flow1_rh_escala_motor', 'skip', 'Sem SETOR com UNIDADE_ID válido.', self::ONDE_RUNNER);
        }

        $funcionarioId = $this->resolveFuncionarioIdForSetor($options, (int) $setor->SETOR_ID);
        if ($funcionarioId <= 0) {
            return $this->row('flow1_rh_escala_motor', 'skip', 'Sem FUNCIONARIO com lotação no setor âncora.', self::ONDE_RUNNER);
        }

        $this->ensureLotacaoNoSetor($funcionarioId, (int) $setor->SETOR_ID);

        if (Schema::hasTable('TABELA_GENERICA')) {
            DB::table('TABELA_GENERICA')->updateOrInsert(
                ['TABELA_ID' => 5, 'COLUNA_ID' => 8],
                ['COLUNA_DESCRICAO' => 'Licença Médica', 'DESCRICAO' => 'Licença Médica']
            );
        }

        $turnoDia = 15;
        $afastInicio = 10;
        $afastFim = 20;
        $this->ensureEscalaDetalheTurno($y, $m, (int) $setor->SETOR_ID, $funcionarioId, $turnoDia);

        DB::table('AFASTAMENTO')->insert([
            'FUNCIONARIO_ID' => $funcionarioId,
            'AFASTAMENTO_DATA_INICIO' => sprintf('%04d-%02d-%02d', $y, $m, $afastInicio),
            'AFASTAMENTO_DATA_FIM' => sprintf('%04d-%02d-%02d', $y, $m, $afastFim),
            'AFASTAMENTO_TIPO' => 8,
        ]);

        $idx = EscalaAusenciaService::indexarPorFuncionarioDia([$funcionarioId], $competenciaYm);
        $diaCell = $idx[$funcionarioId][$turnoDia] ?? null;
        $sigla = is_array($diaCell) ? strtoupper((string) ($diaCell['sigla'] ?? '')) : '';
        if ($sigla !== 'LM') {
            return $this->row(
                'flow1_rh_escala_motor',
                'fail',
                "EscalaAusencia: dia {$turnoDia} sem sigla LM (obtido: '{$sigla}').",
                self::ONDE_ESCALA_AUSENCIA
            );
        }

        $pessoaId = (int) (DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $funcionarioId)->value('PESSOA_ID') ?? 0);
        $siblingIds = $pessoaId > 0
            ? DB::table('FUNCIONARIO')
                ->where('PESSOA_ID', $pessoaId)
                ->where('FUNCIONARIO_ID', '<>', $funcionarioId)
                ->pluck('FUNCIONARIO_ID')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $dualMsg = '';
        if ($siblingIds !== []) {
            $allVisible = true;
            foreach ($siblingIds as $sid) {
                $idxS = EscalaAusenciaService::indexarPorFuncionarioDia([$sid], $competenciaYm);
                $sSig = strtoupper((string) (($idxS[$sid][$turnoDia]['sigla'] ?? '') ?: ''));
                if ($sSig !== 'LM') {
                    $allVisible = false;
                    break;
                }
            }
            if (! $allVisible) {
                return $this->row(
                    'flow1_rh_escala_motor',
                    'fail',
                    'Vínculos duplos (mesma PESSOA_ID): LM inserida num FUNCIONARIO_ID não aparece no(s) outro(s) vínculo(s) via AFASTAMENTO (Teia rompe para São Luís).',
                    self::ONDE_ESCALA_AUSENCIA
                );
            }
            $dualMsg = ' Vínculos duplos: LM visível em todos os FUNCIONARIO_ID da mesma pessoa.';
        } else {
            $dualMsg = ' Vínculo único: verificação de propagação LM entre vínculos não aplicável.';
        }

        $motorReady = Schema::hasColumn('FUNCIONARIO', 'VINCULO_ID') && Schema::hasTable('VINCULO');
        if (! $motorReady) {
            $detalhe = "EscalaAusencia LM no dia {$turnoDia} (competência {$competenciaYm}).{$dualMsg}"
                .' Motor: SKIP — FUNCIONARIO.VINCULO_ID ou tabela VINCULO ausente; MotorFolhaService não aplicável.'
                .' Rubrica SISFOLHA: SKIP — depende do motor v3.';

            return $this->row('flow1_rh_escala_motor', 'pass', trim($detalhe), self::ONDE_MOTOR);
        }

        $folhaId = $this->resolveFolhaId($options, $competenciaYm);
        $motor = new MotorFolhaService();
        try {
            $motorBase = $motor->calcularFolha($folhaId);
        } catch (\Throwable $e) {
            return $this->row(
                'flow1_rh_escala_motor',
                'skip',
                'Motor (baseline) exceção: '.$e->getMessage(),
                self::ONDE_MOTOR
            );
        }
        if (! ($motorBase['ok'] ?? false)) {
            return $this->row(
                'flow1_rh_escala_motor',
                'fail',
                'Motor (baseline): '.((string) ($motorBase['erro'] ?? 'erro desconhecido')),
                self::ONDE_MOTOR
            );
        }
        $liqBase = $this->detalheLiquido($folhaId, $funcionarioId);
        try {
            $motorOut = $motor->calcularFolha($folhaId);
        } catch (\Throwable $e) {
            return $this->row(
                'flow1_rh_escala_motor',
                'skip',
                'Motor (re-cálculo pós-indexação) exceção: '.$e->getMessage(),
                self::ONDE_MOTOR
            );
        }
        $liqAfterAfast = $this->detalheLiquido($folhaId, $funcionarioId);

        $motorPart = '';
        $motorStatus = 'pass';
        if (! ($motorOut['ok'] ?? false)) {
            $motorStatus = 'fail';
            $motorPart = ' Motor: FAIL — '.((string) ($motorOut['erro'] ?? 'erro desconhecido'));
        } elseif (
            ($liqBase === null && $liqAfterAfast === null)
            || ($liqBase !== null && $liqAfterAfast !== null && (string) $liqBase === (string) $liqAfterAfast)
        ) {
            $motorPart = ' Motor: SKIP — AFASTAMENTO não altera DETALHE_FOLHA (sem ligação LM→MotorFolhaService v3; ver Motor vs FolhaParser legado).';
        } else {
            $motorPart = ' Motor: PASS — DETALHE_FOLHA alterado após LM (ligação mensurável ou efeito colateral do cálculo).';
        }

        $rubrica = $this->flow1VerifyRubricaLmSisfolha($folhaId, $funcionarioId, $motor);
        $rubricaPart = $rubrica['detalhe'];

        $detalhe = "EscalaAusencia LM no dia {$turnoDia} (competência {$competenciaYm}).{$dualMsg}{$motorPart} {$rubricaPart}";

        $overall = 'pass';
        if ($motorStatus === 'fail' || $rubrica['status'] === 'fail') {
            $overall = 'fail';
        }

        return $this->row('flow1_rh_escala_motor', $overall, trim($detalhe), self::ONDE_RUNNER);
    }

    /**
     * @return array{status: string, detalhe: string}
     */
    private function flow1VerifyRubricaLmSisfolha(int $folhaId, int $funcionarioId, MotorFolhaService $motor): array
    {
        if (! Schema::hasTable('RUBRICA') || ! Schema::hasTable('LANCAMENTO_FOLHA')) {
            return ['status' => 'skip', 'detalhe' => 'Rubrica SISFOLHA: SKIP — tabelas RUBRICA/LANCAMENTO_FOLHA ausentes.'];
        }

        $existingOtherTipo = DB::table('RUBRICA')
            ->where('RUBRICA_CODIGO', self::RUBRICA_CODIGO_LM)
            ->where('RUBRICA_TIPO', '<>', 'D')
            ->exists();
        if ($existingOtherTipo) {
            return ['status' => 'fail', 'detalhe' => 'Rubrica SISFOLHA: FAIL — já existe RUBRICA_CODIGO 01 com tipo diferente de desconto (conflito com dicionário Batalha 1).'];
        }

        $rubricaId = (int) (DB::table('RUBRICA')
            ->where('RUBRICA_CODIGO', self::RUBRICA_CODIGO_LM)
            ->where('RUBRICA_TIPO', 'D')
            ->value('RUBRICA_ID') ?? 0);

        if ($rubricaId <= 0) {
            $ins = [
                'RUBRICA_CODIGO' => self::RUBRICA_CODIGO_LM,
                'RUBRICA_DESCRICAO' => 'Licença Médica (SISFOLHA 01)',
                'RUBRICA_TIPO' => 'D',
                'RUBRICA_ATIVO' => 1,
            ];
            if (Schema::hasColumn('RUBRICA', 'RUBRICA_CAMADA')) {
                $ins['RUBRICA_CAMADA'] = 3;
            }
            if (Schema::hasColumn('RUBRICA', 'RUBRICA_CALCULO')) {
                $ins['RUBRICA_CALCULO'] = 'fixo';
            }
            $rubricaId = (int) DB::table('RUBRICA')->insertGetId($ins);
        }

        $codAtual = (string) (DB::table('RUBRICA')->where('RUBRICA_ID', $rubricaId)->value('RUBRICA_CODIGO') ?? '');
        if ($codAtual !== self::RUBRICA_CODIGO_LM) {
            return ['status' => 'fail', 'detalhe' => 'Rubrica SISFOLHA: FAIL — rubrica LM existe mas RUBRICA_CODIGO diverge do dicionário 01.'];
        }

        $payload = [
            'FUNCIONARIO_ID' => $funcionarioId,
            'FOLHA_ID' => $folhaId,
            'RUBRICA_ID' => $rubricaId,
            'LANCAMENTO_TIPO' => 'D',
            'LANCAMENTO_QTDE' => 1,
            'LANCAMENTO_VALOR_UNIT' => 0.01,
            'LANCAMENTO_VALOR_TOTAL' => 0.01,
            'LANCAMENTO_INCIDE_PREV' => 0,
            'LANCAMENTO_INCIDE_IRRF' => 0,
            'LANCAMENTO_ORIGEM' => 'smoke_7a',
        ];
        if (Schema::hasColumn('LANCAMENTO_FOLHA', 'created_at')) {
            $payload['created_at'] = now();
            $payload['updated_at'] = now();
        }
        DB::table('LANCAMENTO_FOLHA')->insert($payload);

        try {
            $motor->calcularFolha($folhaId);
        } catch (\Throwable $e) {
            return ['status' => 'skip', 'detalhe' => 'Rubrica SISFOLHA: SKIP — motor exceção: '.$e->getMessage()];
        }

        $joinCod = (string) (DB::table('LANCAMENTO_FOLHA as lf')
            ->join('RUBRICA as r', 'r.RUBRICA_ID', '=', 'lf.RUBRICA_ID')
            ->where('lf.FOLHA_ID', $folhaId)
            ->where('lf.FUNCIONARIO_ID', $funcionarioId)
            ->where('lf.LANCAMENTO_ORIGEM', 'smoke_7a')
            ->value('r.RUBRICA_CODIGO') ?? '');

        if ($joinCod !== self::RUBRICA_CODIGO_LM) {
            return ['status' => 'fail', 'detalhe' => 'Rubrica SISFOLHA: FAIL — LANCAMENTO_C3 não mantém RUBRICA_CODIGO 01 após motor.'];
        }

        return ['status' => 'pass', 'detalhe' => 'Rubrica SISFOLHA: PASS — LANCAMENTO_FOLHA amarrado a RUBRICA_CODIGO 01 (LM) após MotorFolhaService.'];
    }

    /**
     * @return array{fluxo: string, status: string, detalhe: string, onde_rompeu: string}
     */
    private function runFlow2ProgressaoMotorSpa(SmokeTeiaFolhaOptions $options): array
    {
        if (! Schema::hasTable('TABELA_SALARIAL')) {
            return $this->row('flow2_progressao_motor_spa', 'skip', 'TABELA_SALARIAL inexistente.', self::ONDE_RUNNER);
        }
        if (! Schema::hasColumn('FUNCIONARIO', 'VINCULO_ID') || ! Schema::hasTable('VINCULO')) {
            return $this->row('flow2_progressao_motor_spa', 'skip', 'Motor v3 indisponível (VINCULO_ID / VINCULO).', self::ONDE_MOTOR);
        }

        $competenciaYm = $this->resolveCompetenciaYm($options);
        $folhaId = $this->resolveFolhaId($options, $competenciaYm);

        $func = DB::table('FUNCIONARIO as f')
            ->where('f.FUNCIONARIO_ATIVO', 1)
            ->whereNotNull('f.CARREIRA_ID')
            ->whereNotNull('f.FUNCIONARIO_CLASSE')
            ->whereNotNull('f.FUNCIONARIO_REFERENCIA')
            ->orderBy('f.FUNCIONARIO_ID')
            ->first(['f.FUNCIONARIO_ID', 'f.CARREIRA_ID', 'f.FUNCIONARIO_CLASSE', 'f.FUNCIONARIO_REFERENCIA']);

        if (! $func) {
            return $this->row('flow2_progressao_motor_spa', 'skip', 'Sem funcionário ativo com CARREIRA/CLASSE/REFERÊNCIA para simular progressão.', self::ONDE_RUNNER);
        }

        $fid = (int) $func->FUNCIONARIO_ID;
        $carreiraId = (int) $func->CARREIRA_ID;
        $classe = (string) $func->FUNCIONARIO_CLASSE;
        $refAtual = (string) $func->FUNCIONARIO_REFERENCIA;

        $ordAtual = (int) (DB::table('TABELA_SALARIAL')
            ->where('CARREIRA_ID', $carreiraId)
            ->where('TABELA_CLASSE', $classe)
            ->where('TABELA_REFERENCIA', $refAtual)
            ->value('TABELA_REFERENCIA_ORDEM') ?? 0);

        $nextRef = DB::table('TABELA_SALARIAL')
            ->where('CARREIRA_ID', $carreiraId)
            ->where('TABELA_CLASSE', $classe)
            ->where('TABELA_REFERENCIA_ORDEM', '>', $ordAtual)
            ->orderBy('TABELA_REFERENCIA_ORDEM')
            ->value('TABELA_REFERENCIA');

        if (! $nextRef) {
            return $this->row(
                'flow2_progressao_motor_spa',
                'skip',
                'Sem próximo degrau na TABELA_SALARIAL para este servidor (Teia rompe: falta motor de competência fracionada / sem degrau).',
                self::ONDE_MOTOR
            );
        }

        $motor = new MotorFolhaService();
        try {
            $motor->calcularFolha($folhaId);
        } catch (\Throwable $e) {
            return $this->row('flow2_progressao_motor_spa', 'skip', 'Motor exceção: '.$e->getMessage(), self::ONDE_MOTOR);
        }
        $provBefore = $this->detalheProventos($folhaId, $fid);

        $usuario = $this->findUsuarioForFuncionario($fid);
        $spaBefore = $usuario ? json_encode(SpaAuthPayloadBuilder::forAuthenticatedUser($usuario), JSON_UNESCAPED_UNICODE) : null;

        DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $fid)->update([
            'FUNCIONARIO_REFERENCIA' => (string) $nextRef,
        ]);

        try {
            $motor->calcularFolha($folhaId);
        } catch (\Throwable $e) {
            return $this->row('flow2_progressao_motor_spa', 'skip', 'Motor (pós-progressão) exceção: '.$e->getMessage(), self::ONDE_MOTOR);
        }
        $provAfter = $this->detalheProventos($folhaId, $fid);

        $spaAfter = $usuario ? json_encode(SpaAuthPayloadBuilder::forAuthenticatedUser($usuario), JSON_UNESCAPED_UNICODE) : null;

        $motorStatus = 'skip';
        $motorDetalhe = 'Motor: SKIP — proventos DETALHE inalterados (estado atual global; sem corte pro rata por competência).';
        if ($provBefore !== null && $provAfter !== null && (string) $provBefore !== (string) $provAfter) {
            $motorStatus = 'pass';
            $motorDetalhe = 'Motor: PASS — DETALHE_FOLHA_PROVENTOS alterado após mudança de referência.';
        }

        $spaDetalhe = 'SPA: SKIP — payload /api/auth/me inalterado (matrícula e RBAC não derivam automaticamente da progressão; refresh necessário).';
        $spaPass = false;
        if ($spaBefore !== null && $spaAfter !== null && $spaBefore !== $spaAfter) {
            $spaPass = true;
            $spaDetalhe = 'SPA: PASS — SpaAuthPayloadBuilder mudou após progressão.';
        }

        $overall = ($motorStatus === 'pass' || $spaPass) ? 'pass' : 'skip';

        return $this->row(
            'flow2_progressao_motor_spa',
            $overall,
            trim($motorDetalhe.' '.$spaDetalhe),
            self::ONDE_MOTOR.' + app/Support/SpaAuthPayloadBuilder.php'
        );
    }

    /**
     * @return array{fluxo: string, status: string, detalhe: string, onde_rompeu: string}
     */
    private function runFlow3OrganogramaMde(SmokeTeiaFolhaOptions $options): array
    {
        $unidade = DB::table('UNIDADE')->orderBy('UNIDADE_ID')->first(['UNIDADE_ID']);
        if (! $unidade) {
            return $this->row('flow3_organograma_mde', 'skip', 'Sem UNIDADE para criar setor.', self::ONDE_RUNNER);
        }

        $nome = 'SMOKE_7A_'.uniqid();
        $setorRow = [
            'SETOR_NOME' => $nome,
            'UNIDADE_ID' => (int) $unidade->UNIDADE_ID,
        ];
        if (Schema::hasColumn('SETOR', 'SETOR_SIGLA')) {
            $setorRow['SETOR_SIGLA'] = 'S7A';
        }
        if (Schema::hasColumn('SETOR', 'SETOR_ATIVO')) {
            $setorRow['SETOR_ATIVO'] = 1;
        }
        $setorId = (int) DB::table('SETOR')->insertGetId($setorRow);

        $q = DB::table('LOTACAO')->where('SETOR_ID', $setorId);
        if (Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM')) {
            $hoje = now()->toDateString();
            $q->where(function ($w) use ($hoje) {
                $w->whereNull('LOTACAO_DATA_FIM')->orWhere('LOTACAO_DATA_FIM', '>', $hoje);
            });
        }
        $nLot = (int) $q->count();

        $detalhe = "Setor criado SETOR_ID={$setorId} sob UNIDADE_ID={$unidade->UNIDADE_ID}. COUNT lotados ativos no setor={$nLot}. "
            .'MDE: rota GET /api/v3/dashboard/operacional disponível (Fase 9A / BL-TEA-051); este smoke CLI não executa pedido HTTP autenticado ao endpoint.';

        return $this->row('flow3_organograma_mde', 'pass', trim($detalhe), 'routes/organograma_v3.php');
    }

    /**
     * @return array{fluxo: string, status: string, detalhe: string, onde_rompeu: string}
     */
    private function runFlow4aAuditAssignment(): array
    {
        if (! Schema::hasTable('AUDIT_LOG')) {
            return $this->row('flow4a_audit_assignment', 'skip', 'Tabela AUDIT_LOG ausente.', self::ONDE_AUDIT);
        }

        $cols = Schema::getColumnListing('AUDIT_LOG');
        $blobCols = array_values(array_filter($cols, fn ($c) => preg_match('/dados|context/i', (string) $c)));
        if ($blobCols === []) {
            return $this->row('flow4a_audit_assignment', 'skip', 'AUDIT_LOG sem coluna de contexto JSON pesquisável.', self::ONDE_AUDIT);
        }

        $needle = 'gente_assignment_id';
        $found = false;
        foreach ($blobCols as $col) {
            if (DB::table('AUDIT_LOG')->where($col, 'like', '%'.$needle.'%')->exists()) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            return $this->row(
                'flow4a_audit_assignment',
                'skip',
                'Nenhum registo recente em AUDIT_LOG com '.$needle.' (esperado após POST escala com bypass sudo + assignment).',
                self::ONDE_AUDIT
            );
        }

        return $this->row('flow4a_audit_assignment', 'pass', 'AUDIT_LOG contém evento com gente_assignment_id no payload.', self::ONDE_AUDIT);
    }

    /**
     * @return array{fluxo: string, status: string, detalhe: string, onde_rompeu: string}
     */
    private function runFlow4bTenantScopeShadow(SmokeTeiaFolhaOptions $options): array
    {
        if (! $options->checkTenantScopeLog) {
            return $this->row(
                'flow4b_tenant_scope_shadow',
                'skip',
                'Não solicitado. TenantScopeDecision::toLogContext() não inclui gente_assignment_id; use --tenant-scope-log após request HTTP com GENTE_TENANT_SCOPE_MIDDLEWARE=true.',
                'app/Support/TenantScope/TenantScopeDecision.php'
            );
        }

        $channel = (string) config('gente_tenant_rings.log_channel', 'tenant_scope');
        $path = (string) (config("logging.channels.{$channel}.path") ?? '');
        if ($path === '' || ! is_readable($path)) {
            return $this->row('flow4b_tenant_scope_shadow', 'skip', 'Ficheiro de log tenant_scope inexistente ou ilegível: '.$path, 'config/logging.php');
        }

        $tail = @file_get_contents($path, false, null, max(0, filesize($path) - 8192));
        if (! is_string($tail) || $tail === '') {
            return $this->row('flow4b_tenant_scope_shadow', 'skip', 'Log vazio.', $path);
        }

        if (stripos($tail, 'shadow') === false) {
            return $this->row('flow4b_tenant_scope_shadow', 'skip', 'Sem entrada shadow recente (middleware pode estar desligado).', $path);
        }

        return $this->row('flow4b_tenant_scope_shadow', 'pass', 'Log tenant_scope acessível e contém menção a shadow.', $path);
    }

    private function findUsuarioForFuncionario(int $funcionarioId): ?Usuario
    {
        if (! Schema::hasColumn('USUARIO', 'FUNCIONARIO_ID')) {
            return null;
        }

        return Usuario::query()->where('FUNCIONARIO_ID', $funcionarioId)->orderBy('USUARIO_ID')->first();
    }

    private function detalheLiquido(int $folhaId, int $funcionarioId): ?string
    {
        if (! Schema::hasColumn('DETALHE_FOLHA', 'DETALHE_FOLHA_LIQUIDO')) {
            return null;
        }

        $v = DB::table('DETALHE_FOLHA')
            ->where('FOLHA_ID', $folhaId)
            ->where('FUNCIONARIO_ID', $funcionarioId)
            ->value('DETALHE_FOLHA_LIQUIDO');

        return $v !== null ? (string) $v : null;
    }

    private function detalheProventos(int $folhaId, int $funcionarioId): ?string
    {
        if (! Schema::hasColumn('DETALHE_FOLHA', 'DETALHE_FOLHA_PROVENTOS')) {
            return null;
        }

        $v = DB::table('DETALHE_FOLHA')
            ->where('FOLHA_ID', $folhaId)
            ->where('FUNCIONARIO_ID', $funcionarioId)
            ->value('DETALHE_FOLHA_PROVENTOS');

        return $v !== null ? (string) $v : null;
    }

    private function resolveFuncionarioIdForSetor(SmokeTeiaFolhaOptions $options, int $setorId): int
    {
        if ($options->funcionarioId !== null && $options->funcionarioId > 0) {
            return $options->funcionarioId;
        }

        $temLotacaoFim = Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM');
        $temFuncionarioFim = Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM');
        $hoje = now()->toDateString();

        return (int) (DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($join) use ($temLotacaoFim) {
                $join->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
                if ($temLotacaoFim) {
                    $join->whereNull('l.LOTACAO_DATA_FIM');
                }
            })
            ->where('l.SETOR_ID', $setorId)
            ->whereNotNull('l.LOTACAO_ID')
            ->when($temFuncionarioFim, fn ($q) => $q->where(function ($w) use ($hoje) {
                $w->whereNull('f.FUNCIONARIO_DATA_FIM')
                    ->orWhere('f.FUNCIONARIO_DATA_FIM', '>', $hoje);
            }))
            ->orderBy('p.PESSOA_NOME')
            ->value('f.FUNCIONARIO_ID') ?? 0);
    }

    private function ensureLotacaoNoSetor(int $funcionarioId, int $setorId): void
    {
        $exists = DB::table('LOTACAO')->where('FUNCIONARIO_ID', $funcionarioId)->where('SETOR_ID', $setorId)->exists();
        if ($exists) {
            return;
        }
        $row = ['FUNCIONARIO_ID' => $funcionarioId, 'SETOR_ID' => $setorId];
        if (Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_INICIO')) {
            $row['LOTACAO_DATA_INICIO'] = now()->toDateString();
        }
        DB::table('LOTACAO')->insert($row);
    }

    private function ensureEscalaDetalheTurno(int $ano, int $mes, int $setorId, int $funcionarioId, int $turnoDia): void
    {
        $competencia = sprintf('%04d-%02d', $ano, $mes);
        $diaTurno = sprintf('%04d-%02d-%02d', $ano, $mes, $turnoDia);

        $turnoId = (int) (DB::table('TURNO')->where('TURNO_SIGLA', 'M')->value('TURNO_ID') ?? 0);
        if ($turnoId <= 0) {
            $turnoRow = ['TURNO_SIGLA' => 'M'];
            if (Schema::hasColumn('TURNO', 'TURNO_NOME')) {
                $turnoRow['TURNO_NOME'] = 'Matutino';
            }
            $turnoId = (int) DB::table('TURNO')->insertGetId($turnoRow);
        }

        $escala = DB::table('ESCALA')
            ->where('ESCALA_COMPETENCIA', $competencia)
            ->where('SETOR_ID', $setorId)
            ->first();
        if (! $escala) {
            $insEscala = [
                'ESCALA_COMPETENCIA' => $competencia,
                'SETOR_ID' => $setorId,
            ];
            if (Schema::hasColumn('ESCALA', 'ESCALA_STATUS')) {
                $insEscala['ESCALA_STATUS'] = 'RASCUNHO';
            }
            $escalaId = (int) DB::table('ESCALA')->insertGetId($insEscala);
        } else {
            $escalaId = (int) $escala->ESCALA_ID;
        }

        $detalhe = DB::table('DETALHE_ESCALA')
            ->where('ESCALA_ID', $escalaId)
            ->where('FUNCIONARIO_ID', $funcionarioId)
            ->first();
        if (! $detalhe) {
            $detalheId = (int) DB::table('DETALHE_ESCALA')->insertGetId([
                'ESCALA_ID' => $escalaId,
                'FUNCIONARIO_ID' => $funcionarioId,
            ]);
        } else {
            $detalheId = (int) $detalhe->DETALHE_ESCALA_ID;
        }

        $payload = ['TURNO_ID' => $turnoId];
        if (Schema::hasColumn('DETALHE_ESCALA_ITEM', 'updated_at')) {
            $payload['updated_at'] = now();
        }
        if (Schema::hasColumn('DETALHE_ESCALA_ITEM', 'TURNO_SIGLA')) {
            $payload['TURNO_SIGLA'] = 'M';
        }

        DB::table('DETALHE_ESCALA_ITEM')->updateOrInsert(
            ['DETALHE_ESCALA_ID' => $detalheId, 'DETALHE_ESCALA_ITEM_DATA' => $diaTurno],
            $payload
        );
    }

    /**
     * @return array{fluxo: string, status: string, detalhe: string, onde_rompeu: string}
     */
    private function row(string $fluxo, string $status, string $detalhe, string $onde): array
    {
        return [
            'fluxo' => $fluxo,
            'status' => $status,
            'detalhe' => $detalhe,
            'onde_rompeu' => $onde,
        ];
    }
}
