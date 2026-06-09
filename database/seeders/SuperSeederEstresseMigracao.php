<?php

namespace Database\Seeders;

use App\Domain\Escala\EscalaWorkflowStatus;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Teste de carga alinhado ao de-para SISFOLHA → GENTE (ver docs/MIGRACAO_SISFOLHA_GENTE_DEPARA.md).
 *
 * Por omissão **não** corre no `php artisan db:seed` sem flag. Com `GENTE_STRESS_SEED=1`, o {@see SecretariasSeed}
 * invoca este seeder no final (Docker de homolog já define a flag).
 *
 * Execução manual:
 *
 *   GENTE_STRESS_SEED=1 GENTE_STRESS_N=30000 GENTE_STRESS_AUDIT=50000 php artisan db:seed --class=SuperSeederEstresseMigracao
 *
 * Variáveis opcionais: GENTE_STRESS_CHUNK (padrão 150), GENTE_STRESS_COMP (padrão mês corrente Y-m).
 * DETALHE_ESCALA_ITEM: nunca inclui coluna `updated_at` no *payload* de inserção (só colunas mínimas + `created_at` se NOT NULL no schema).
 */
class SuperSeederEstresseMigracao extends Seeder
{
    private int $chunkSize = 150;

    private int $totalServidores = 30000;

    private int $totalAudit = 50000;

    public function run(): void
    {
        if (! filter_var(env('GENTE_STRESS_SEED', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->command?->warn('Pulado: defina GENTE_STRESS_SEED=1 no .env (apenas homolog/stress).');

            return;
        }

        $this->totalServidores = max(1, (int) env('GENTE_STRESS_N', 30000));
        $this->totalAudit = max(0, (int) env('GENTE_STRESS_AUDIT', 50000));
        $this->chunkSize = max(100, min(1000, (int) env('GENTE_STRESS_CHUNK', 150)));

        $tabelas = ['PESSOA', 'FUNCIONARIO', 'LOTACAO', 'ESCALA', 'DETALHE_ESCALA', 'UNIDADE', 'SETOR'];
        foreach ($tabelas as $t) {
            if (! Schema::hasTable($t)) {
                throw new RuntimeException("Tabela {$t} ausente — rode os seeders base antes.");
            }
        }
        if (! Schema::hasTable('DETALHE_ESCALA_ITEM') || ! Schema::hasTable('TURNO')) {
            throw new RuntimeException('DETALHE_ESCALA_ITEM e TURNO são obrigatórios para este seeder.');
        }
        if (! Schema::hasTable('PCCV_DOMINIO') || ! Schema::hasTable('CARGO')) {
            throw new RuntimeException('PCCV_DOMINIO e CARGO são obrigatórios (PccvDominioSeeder + tabela CARGO).');
        }

        $comp = $this->normalizarCompetencia((string) env('GENTE_STRESS_COMP', now()->format('Y-m')));

        $this->command?->info("SuperSeeder estresse: N={$this->totalServidores}, chunk={$this->chunkSize}, competência={$comp}, audit={$this->totalAudit}");

        $ctx = $this->bootstrapContext($comp);
        $setorMap = $this->buildSetorMap($this->totalServidores);

        $vinculoId = $this->firstVinculoId();
        $turnoBySigla = $this->turnoMapSiglaId();
        $turnoM = (int) ($turnoBySigla['M'] ?? 0);
        if (! $turnoM) {
            throw new RuntimeException('Nenhum TURNO com sigla M encontrado.');
        }

        $pessoaCpfCol = $this->cpfColumn();
        $baseSeq = (int) (now()->format('Y') * 1_000_000);

        for ($start = 0; $start < $this->totalServidores; $start += $this->chunkSize) {
            $n = min($this->chunkSize, $this->totalServidores - $start);
            DB::transaction(function () use ($ctx, $setorMap, $vinculoId, $turnoBySigla, $pessoaCpfCol, $comp, $baseSeq, $start, $n) {
                $this->seedChunk(
                    $ctx,
                    $setorMap,
                    $vinculoId,
                    $turnoBySigla,
                    $pessoaCpfCol,
                    $comp,
                    $baseSeq,
                    $start,
                    $n
                );
            });
        }

        if ($this->totalAudit > 0) {
            $this->seedAuditBatches($ctx, $comp, $turnoM);
        }

        $this->command?->info('SuperSeeder estresse concluído.');
    }

    /**
     * @return array{setores: array{semed: int, semus: int, geral: int, classificar: int}, escalaBySetor: array<int, int>, cargos: array{semed: int, semus: int, geral: int}, semed: int, semus: int, geral: int, comp: string}
     */
    private function bootstrapContext(string $comp): array
    {
        $setorSemed = $this->setorForUnidadeSigla('SEMED');
        $setorSemus = $this->setorForUnidadeSigla('SEMUS');
        $setorGeral = $this->setorForUnidadeSigla('SEMAD') ?? $this->setorForUnidadeSigla('GABPREF') ?? $setorSemed;
        if (! $setorSemed || ! $setorSemus || ! $setorGeral) {
            throw new RuntimeException('Não foi possível resolver SETOR para SEMED, SEMUS e unidade geral (SEMAD/GABPREF).');
        }
        $setorClass = $this->ensureSetorAClassificar();

        $cargos = [
            'semed' => $this->resolveCargoPccv('MAGISTERIO', 'Professor (stress Magistério)'),
            'semus' => $this->resolveCargoPccv('SAUDE', 'Técnico (stress Saúde)'),
            'geral' => $this->resolveCargoPccv('GERAL', 'Analista (stress Regime geral)'),
        ];

        $setores = [
            'semed' => $setorSemed,
            'semus' => $setorSemus,
            'geral' => $setorGeral,
            'classificar' => $setorClass,
        ];

        $escalaBySetor = [];
        foreach ($setores as $id) {
            $escalaBySetor[$id] = $this->ensureEscala($id, $comp);
        }

        return [
            'setores' => $setores,
            'escalaBySetor' => $escalaBySetor,
            'cargos' => $cargos,
            'semed' => $setorSemed,
            'semus' => $setorSemus,
            'geral' => $setorGeral,
            'comp' => $comp,
        ];
    }

    private function setorForUnidadeSigla(string $sigla): ?int
    {
        $uid = (int) (DB::table('UNIDADE')->where('UNIDADE_SIGLA', $sigla)->value('UNIDADE_ID') ?? 0);
        if (! $uid) {
            return null;
        }

        return (int) (DB::table('SETOR')
            ->where('UNIDADE_ID', $uid)
            ->orderBy('SETOR_ID')
            ->value('SETOR_ID') ?? 0) ?: null;
    }

    private function ensureSetorAClassificar(): int
    {
        $uid = (int) (DB::table('UNIDADE')->where('UNIDADE_SIGLA', 'MIG-NAO-CLASS')->value('UNIDADE_ID') ?? 0);
        if (! $uid) {
            $uCols = Schema::getColumnListing('UNIDADE');
            $u = [
                'UNIDADE_NOME' => 'Migração — A CLASSIFICAR (stress)',
                'UNIDADE_SIGLA' => 'MIG-NAO-CLASS',
            ];
            if (in_array('UNIDADE_ATIVA', $uCols, true)) {
                $u['UNIDADE_ATIVA'] = 1;
            }
            if (in_array('UNIDADE_ATIVO', $uCols, true)) {
                $u['UNIDADE_ATIVO'] = 1;
            }
            $uid = (int) DB::table('UNIDADE')->insertGetId($u);
        }
        $sid = (int) (DB::table('SETOR')
            ->where('UNIDADE_ID', $uid)
            ->where('SETOR_NOME', 'A CLASSIFICAR (stress)')
            ->value('SETOR_ID') ?? 0);
        if ($sid) {
            return $sid;
        }
        $s = [
            'UNIDADE_ID' => $uid,
            'SETOR_NOME' => 'A CLASSIFICAR (stress)',
        ];
        if (in_array('SETOR_ATIVO', Schema::getColumnListing('SETOR'), true)) {
            $s['SETOR_ATIVO'] = 1;
        }

        return (int) DB::table('SETOR')->insertGetId($s);
    }

    private function ensureEscala(int $setorId, string $comp): int
    {
        $colsEscala = Schema::getColumnListing('ESCALA');
        $ex = DB::table('ESCALA')
            ->where('ESCALA_COMPETENCIA', $comp)
            ->where('SETOR_ID', $setorId)
            ->first();
        if ($ex) {
            return (int) $ex->ESCALA_ID;
        }
        $dados = [
            'ESCALA_COMPETENCIA' => $comp,
            'SETOR_ID' => $setorId,
        ];
        if (in_array('ESCALA_STATUS', $colsEscala, true)) {
            $dados['ESCALA_STATUS'] = EscalaWorkflowStatus::RASCUNHO;
        }
        if (in_array('ESCALA_ATIVO', $colsEscala, true)) {
            $dados['ESCALA_ATIVO'] = 1;
        }
        if (in_array('ESCALA_OBSERVACAO', $colsEscala, true)) {
            $dados['ESCALA_OBSERVACAO'] = 'Escala stress SuperSeederEstresseMigracao';
        }
        if (in_array('ESCALA_DESCRICAO', $colsEscala, true)) {
            $dados['ESCALA_DESCRICAO'] = "Escala {$comp} (stress)";
        }
        if (in_array('TIPO_ESCALA_ID', $colsEscala, true) && Schema::hasTable('TIPO_ESCALA')) {
            $dados['TIPO_ESCALA_ID'] = (int) (DB::table('TIPO_ESCALA')->value('TIPO_ESCALA_ID') ?? 0) ?: 1;
        }
        if (in_array('created_at', $colsEscala, true)) {
            $dados['created_at'] = now();
        }
        if (in_array('updated_at', $colsEscala, true)) {
            $dados['updated_at'] = now();
        }

        return (int) DB::table('ESCALA')->insertGetId($dados);
    }

    private function resolveCargoPccv(string $siglaPccv, string $nome): int
    {
        $pccvId = (int) (DB::table('PCCV_DOMINIO')->where('SIGLA', $siglaPccv)->value('PCCV_DOMINIO_ID') ?? 0);
        if (! $pccvId) {
            throw new RuntimeException("PCCV_DOMINIO.SIGLA={$siglaPccv} não encontrado.");
        }
        $cols = Schema::getColumnListing('CARGO');
        $ex = DB::table('CARGO')->where('CARGO_NOME', $nome)->first();
        if ($ex) {
            if (in_array('PCCV_ID', $cols, true)) {
                DB::table('CARGO')->where('CARGO_ID', $ex->CARGO_ID)->update(['PCCV_ID' => $pccvId]);
            }

            return (int) $ex->CARGO_ID;
        }
        $row = ['CARGO_NOME' => $nome];
        if (in_array('CARGO_ATIVO', $cols, true)) {
            $row['CARGO_ATIVO'] = 1;
        }
        if (in_array('CARGO_DATA_INICIO', $cols, true)) {
            $row['CARGO_DATA_INICIO'] = '2006-01-01';
        }
        if (in_array('PCCV_ID', $cols, true)) {
            $row['PCCV_ID'] = $pccvId;
        }

        return (int) DB::table('CARGO')->insertGetId($row);
    }

    /**
     * @return array<int, array{setor: int, cargo: int, bucket: string}>
     */
    private function buildSetorMap(int $total): array
    {
        $nSemed = 15000;
        $nSemus = 10000;
        $nGeral = 5000;
        if ($nSemed + $nSemus + $nGeral !== $total) {
            throw new RuntimeException("Repartição interna incompatível com o total de {$total}.");
        }
        $map = [];
        $j = 0;
        for ($i = 0; $i < $total; $i++) {
            if ($i % 20 === 0) {
                $map[$i] = ['setor' => 'classificar', 'cargo' => 'geral', 'bucket' => 'ac'];
                continue;
            }
            if ($j < $nSemed) {
                $map[$i] = ['setor' => 'semed', 'cargo' => 'semed', 'bucket' => 'semed'];
            } elseif ($j < $nSemed + $nSemus) {
                $map[$i] = ['setor' => 'semus', 'cargo' => 'semus', 'bucket' => 'semus'];
            } else {
                $map[$i] = ['setor' => 'geral', 'cargo' => 'geral', 'bucket' => 'geral'];
            }
            $j++;
        }

        return $map;
    }

    private function firstVinculoId(): ?int
    {
        if (! Schema::hasTable('VINCULO')) {
            return null;
        }
        $id = DB::table('VINCULO')->orderBy('VINCULO_ID')->value('VINCULO_ID');

        return $id ? (int) $id : null;
    }

    private function turnoMapSiglaId(): array
    {
        if (! Schema::hasTable('TURNO') || ! Schema::hasColumn('TURNO', 'TURNO_SIGLA')) {
            return [];
        }
        $rows = DB::table('TURNO')
            ->whereNotNull('TURNO_SIGLA')
            ->get(['TURNO_ID', 'TURNO_SIGLA']);
        $map = [];
        foreach ($rows as $r) {
            $sigla = strtoupper(trim((string) ($r->TURNO_SIGLA ?? '')));
            $id = (int) ($r->TURNO_ID ?? 0);
            if ($sigla !== '' && $id > 0) {
                $map[$sigla] = $id;
            }
        }

        if (! isset($map['V']) && isset($map['T'])) {
            $map['V'] = $map['T'];
        }
        if (! isset($map['T']) && isset($map['V'])) {
            $map['T'] = $map['V'];
        }
        if (! isset($map['AT']) && isset($map['AF'])) {
            $map['AT'] = $map['AF'];
        }
        if (! isset($map['AF']) && isset($map['AT'])) {
            $map['AF'] = $map['AT'];
        }

        return $map;
    }

    private function normalizarCompetencia(string $competencia): string
    {
        $competencia = trim($competencia);
        if (preg_match('/^\d{4}-\d{2}$/', $competencia) === 1) {
            return $competencia;
        }
        if (preg_match('/^(\d{2})\/(\d{4})$/', $competencia, $m) === 1) {
            return sprintf('%04d-%02d', (int) $m[2], (int) $m[1]);
        }

        return sprintf('%04d-%02d', (int) now()->year, (int) now()->month);
    }

    private function cpfColumn(): string
    {
        if (Schema::hasColumn('PESSOA', 'PESSOA_CPF_NUMERO')) {
            return 'PESSOA_CPF_NUMERO';
        }
        if (Schema::hasColumn('PESSOA', 'PESSOA_CPF')) {
            return 'PESSOA_CPF';
        }

        return 'PESSOA_CPF_NUMERO';
    }

    /**
     * @param  array{setores: array{semed: int, semus: int, geral: int, classificar: int}, escalaBySetor: array<int, int>, cargos: array{semed: int, semus: int, geral: int}, semed: int, semus: int, geral: int, comp: string}  $ctx
     * @param  array<int, array{setor: int, cargo: int, bucket: string}>  $setorMap
     */
    private function seedChunk(
        array $ctx,
        array $setorMap,
        ?int $vinculoId,
        array $turnoBySigla,
        string $pessoaCpfCol,
        string $comp,
        int $baseSeq,
        int $start,
        int $n
    ): void {
        $ano = (int) substr($comp, 0, 4);
        $mes = (int) substr($comp, 5, 2);
        $diasUteis = [];
        $fimMes = Carbon::create($ano, $mes, 1)->endOfMonth()->day;
        for ($dia = 1; $dia <= $fimMes; $dia++) {
            $dt = Carbon::create($ano, $mes, $dia);
            if (! $dt->isWeekend()) {
                $diasUteis[] = $dt->toDateString();
            }
        }
        if ($diasUteis === []) {
            throw new RuntimeException("Não há dias úteis disponíveis para {$comp}.");
        }

        $pessoaBatch = [];
        $cpfList = [];
        $meta = [];
        for ($k = 0; $k < $n; $k++) {
            $i = $start + $k;
            $seq = $baseSeq + $i;
            $cpf = sprintf('%011d', 100_000_000 + ($seq % 800_000_000));
            $matr = $ano . '-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
            $b = $setorMap[$i] ?? null;
            if (! $b) {
                throw new RuntimeException("Mapa de setor inexistente para i={$i}.");
            }
            $setorId = $ctx['setores'][$b['setor']];
            $cargoId = $ctx['cargos'][$b['cargo']];
            $nome = "Stress SISFOLHA {$seq}";

            $pessoaRow = [
                'PESSOA_NOME' => $nome,
            ];
            if ($pessoaCpfCol === 'PESSOA_CPF') {
                $pessoaRow['PESSOA_CPF'] = $cpf;
            } else {
                $pessoaRow['PESSOA_CPF_NUMERO'] = $cpf;
            }
            if (Schema::hasColumn('PESSOA', 'PESSOA_CPF') && ! isset($pessoaRow['PESSOA_CPF'])) {
                $pessoaRow['PESSOA_CPF'] = $cpf;
            }
            if (Schema::hasColumn('PESSOA', 'PESSOA_SEXO')) {
                $pessoaRow['PESSOA_SEXO'] = 1;
            }
            if (Schema::hasColumn('PESSOA', 'PESSOA_DATA_NASCIMENTO')) {
                $pessoaRow['PESSOA_DATA_NASCIMENTO'] = '1980-01-15';
            }
            if (Schema::hasColumn('PESSOA', 'PESSOA_NASC')) {
                $pessoaRow['PESSOA_NASC'] = '1980-01-15';
            }
            if (Schema::hasColumn('PESSOA', 'PESSOA_ATIVO')) {
                $pessoaRow['PESSOA_ATIVO'] = 1;
            }
            if (Schema::hasColumn('PESSOA', 'PESSOA_DATA_CADASTRO')) {
                $pessoaRow['PESSOA_DATA_CADASTRO'] = now()->toDateString();
            }
            if (in_array('created_at', Schema::getColumnListing('PESSOA'), true)) {
                $pessoaRow['created_at'] = now();
            }
            if (in_array('updated_at', Schema::getColumnListing('PESSOA'), true)) {
                $pessoaRow['updated_at'] = now();
            }
            $pessoaBatch[] = $pessoaRow;
            $cpfList[] = $cpf;
            $meta[] = [
                'setorId' => $setorId,
                'escalaId' => $ctx['escalaBySetor'][$setorId],
                'matr' => $matr,
                'cpf' => $cpf,
                'cargoId' => $cargoId,
            ];
        }

        DB::table('PESSOA')->insert($pessoaBatch);
        $pessoas = DB::table('PESSOA')
            ->whereIn($pessoaCpfCol, $cpfList)
            ->pluck('PESSOA_ID', $pessoaCpfCol)
            ->all();

        $funcBatch = [];
        foreach ($meta as $m) {
            $pid = (int) ($pessoas[$m['cpf']] ?? 0);
            if (! $pid) {
                throw new RuntimeException('Falha ao mapear PESSOA inserida (CPF).');
            }
            $fd = [
                'PESSOA_ID' => $pid,
                'FUNCIONARIO_MATRICULA' => $m['matr'],
                'FUNCIONARIO_DATA_INICIO' => '2010-01-01',
            ];
            if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_ATIVO')) {
                $fd['FUNCIONARIO_ATIVO'] = 1;
            }
            if (Schema::hasColumn('FUNCIONARIO', 'CARGO_ID')) {
                $fd['CARGO_ID'] = $m['cargoId'];
            }
            if (Schema::hasColumn('FUNCIONARIO', 'VINCULO_ID') && $vinculoId) {
                $fd['VINCULO_ID'] = $vinculoId;
            }
            if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_REGIME_PREV')) {
                $fd['FUNCIONARIO_REGIME_PREV'] = 1;
            }
            if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_CADASTRO')) {
                $fd['FUNCIONARIO_DATA_CADASTRO'] = now()->toDateString();
            }
            if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_ATUALIZACAO')) {
                $fd['FUNCIONARIO_DATA_ATUALIZACAO'] = now()->toDateString();
            }
            if (in_array('created_at', Schema::getColumnListing('FUNCIONARIO'), true)) {
                $fd['created_at'] = now();
            }
            if (in_array('updated_at', Schema::getColumnListing('FUNCIONARIO'), true)) {
                $fd['updated_at'] = now();
            }
            $funcBatch[] = $fd;
        }
        DB::table('FUNCIONARIO')->insert($funcBatch);

        $matrList = array_map(static fn ($m) => $m['matr'], $meta);
        $funcIds = DB::table('FUNCIONARIO')
            ->whereIn('FUNCIONARIO_MATRICULA', $matrList)
            ->pluck('FUNCIONARIO_ID', 'FUNCIONARIO_MATRICULA')
            ->all();

        $lotBatch = [];
        $deBatch = [];
        $deiBatch = [];
        $detCols = Schema::getColumnListing('DETALHE_ESCALA');
        $deiCols = Schema::getColumnListing('DETALHE_ESCALA_ITEM');
        $deiHasCreated = in_array('created_at', $deiCols, true);
        if (! isset($turnoBySigla['M'])) {
            throw new RuntimeException('Mapa de turnos inválido: sigla M não encontrada no catálogo TURNO.');
        }

        foreach ($meta as $m) {
            $fid = (int) ($funcIds[$m['matr']] ?? 0);
            if (! $fid) {
                throw new RuntimeException('Falha ao mapear FUNCIONARIO inserido.');
            }
            $ld = [
                'FUNCIONARIO_ID' => $fid,
                'SETOR_ID' => $m['setorId'],
            ];
            if (Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_INICIO')) {
                $ld['LOTACAO_DATA_INICIO'] = '2010-01-01';
            }
            if (Schema::hasColumn('LOTACAO', 'VINCULO_ID') && $vinculoId) {
                $ld['VINCULO_ID'] = $vinculoId;
            }
            if (in_array('created_at', Schema::getColumnListing('LOTACAO'), true)) {
                $ld['created_at'] = now();
            }
            if (in_array('updated_at', Schema::getColumnListing('LOTACAO'), true)) {
                $ld['updated_at'] = now();
            }
            $lotBatch[] = $ld;

            $dDet = [
                'ESCALA_ID' => $m['escalaId'],
                'FUNCIONARIO_ID' => $fid,
            ];
            if (in_array('DETALHE_ESCALA_CARGO', $detCols, true)) {
                $dDet['DETALHE_ESCALA_CARGO'] = 'Cargo stress';
            }
            if (in_array('created_at', $detCols, true)) {
                $dDet['created_at'] = now();
            }
            if (in_array('updated_at', $detCols, true)) {
                $dDet['updated_at'] = now();
            }
            $deBatch[] = $dDet;
        }
        DB::table('LOTACAO')->insert($lotBatch);
        DB::table('DETALHE_ESCALA')->insert($deBatch);

        $fidsChunk = array_values($funcIds);
        $eidsChunk = array_values(array_unique(array_map(static fn ($m) => (int) $m['escalaId'], $meta)));
        $rowsDe = DB::table('DETALHE_ESCALA')
            ->whereIn('FUNCIONARIO_ID', $fidsChunk)
            ->whereIn('ESCALA_ID', $eidsChunk)
            ->get(['FUNCIONARIO_ID', 'ESCALA_ID', 'DETALHE_ESCALA_ID']);
        $detPorFuncEscala = [];
        foreach ($rowsDe as $r) {
            $detPorFuncEscala[(int) $r->FUNCIONARIO_ID . '|' . (int) $r->ESCALA_ID] = (int) $r->DETALHE_ESCALA_ID;
        }
        if (count($rowsDe) < count($meta)) {
            throw new RuntimeException('Falha ao mapear DETALHE_ESCALA recém-inseridos; verifique uq_escala_funcionario e chunk.');
        }

        foreach ($meta as $m) {
            $fid = (int) ($funcIds[$m['matr']] ?? 0);
            $detalheId = (int) ($detPorFuncEscala[$fid . '|' . (int) $m['escalaId']] ?? 0);
            if (! $detalheId) {
                throw new RuntimeException('DETALHE_ESCALA faltando.');
            }
            $siglaTurno = $this->sortearSiglaTurnoRealista($turnoBySigla);
            $turnoId = (int) ($turnoBySigla[$siglaTurno] ?? 0);
            if ($turnoId <= 0) {
                throw new RuntimeException("Catálogo TURNO inválido para sigla {$siglaTurno}.");
            }
            $dataItem = $diasUteis[array_rand($diasUteis)];
            $item = [
                'DETALHE_ESCALA_ID' => $detalheId,
                'DETALHE_ESCALA_ITEM_DATA' => $dataItem,
                'TURNO_ID' => $turnoId,
            ];
            if (in_array('TURNO_SIGLA', $deiCols, true)) {
                $item['TURNO_SIGLA'] = $siglaTurno;
            }
            if ($deiHasCreated) {
                $item['created_at'] = now();
            }
            $deiBatch[] = $item;
        }
        DB::table('DETALHE_ESCALA_ITEM')->insert($deiBatch);
    }

    private function sortearSiglaTurnoRealista(array $turnoBySigla): string
    {
        $pool = [
            'M', 'M', 'M', 'M',
            'V', 'V', 'V',
            'N', 'N',
            'I', 'I',
            'SO',
            'AT',
            'F',
        ];
        $validos = array_values(array_filter($pool, fn ($sigla) => isset($turnoBySigla[$sigla])));
        if ($validos === []) {
            $validos = array_keys($turnoBySigla);
        }
        if ($validos === []) {
            throw new RuntimeException('Não há turnos válidos para distribuição no SuperSeeder.');
        }

        return (string) $validos[array_rand($validos)];
    }

    /**
     * @param  array{setores: array, escalaBySetor: array<int, int>, cargos: array, semed: int, semus: int, geral: int, comp: string}  $ctx
     */
    private function seedAuditBatches(
        array $ctx,
        string $comp,
        int $turnoM
    ): void {
        if (! Schema::hasTable('AUDIT_LOG') || ! Schema::hasTable('MOTIVO_ALTERACAO_DOMINIO')) {
            $this->command?->warn('AUDIT_LOG ou MOTIVO_ALTERACAO_DOMINIO ausente — pulando bloco de auditoria.');

            return;
        }
        $qMot = DB::table('MOTIVO_ALTERACAO_DOMINIO');
        if (Schema::hasColumn('MOTIVO_ALTERACAO_DOMINIO', 'ATIVO')) {
            $qMot->where('ATIVO', 1);
        }
        $motivos = $qMot->orderBy('MOTIVO_ALTERACAO_ID')->pluck('MOTIVO_ALTERACAO_ID')->all();
        if (empty($motivos)) {
            $motivos = [null];
        }
        $userId = (int) (DB::table('USUARIO')->orderBy('USUARIO_ID')->value('USUARIO_ID') ?? 0) ?: 1;
        $setorExemplo = (int) $ctx['semed'];
        $funcExemplo = (int) (DB::table('LOTACAO as l')
            ->where('l.SETOR_ID', $setorExemplo)
            ->orderBy('l.LOTACAO_ID')
            ->value('l.FUNCIONARIO_ID') ?? 0) ?: 1;
        $detExemplo = (int) (DB::table('DETALHE_ESCALA as de')
            ->join('ESCALA as e', 'e.ESCALA_ID', '=', 'de.ESCALA_ID')
            ->where('e.ESCALA_COMPETENCIA', $comp)
            ->orderBy('de.DETALHE_ESCALA_ID')
            ->value('de.DETALHE_ESCALA_ID') ?? 0) ?: 1;
        $escExemplo = (int) (DB::table('ESCALA')
            ->where('ESCALA_COMPETENCIA', $comp)
            ->orderBy('ESCALA_ID')
            ->value('ESCALA_ID') ?? 0) ?: 1;
        $auditCols = Schema::getColumnListing('AUDIT_LOG');
        $byLower = [];
        foreach ($auditCols as $c) {
            $byLower[strtolower($c)] = $c;
        }
        $pick = static function (string ...$names) use ($byLower): ?string {
            foreach ($names as $name) {
                $k = $byLower[strtolower($name)] ?? null;
                if ($k !== null) {
                    return $k;
                }
            }

            return null;
        };

        $h = 0;
        for ($a = 0; $a < $this->totalAudit; $a += $this->chunkSize) {
            $c = min($this->chunkSize, $this->totalAudit - $a);
            $rows = [];
            for ($b = 0; $b < $c; $b++) {
                $h++;
                $mid = $motivos[($h - 1) % count($motivos)];
                $dados = [
                    'ACAO' => 'ESCALA_MIGRACAO_RETROATIVA',
                    'TABELA' => 'DETALHE_ESCALA_ITEM',
                    'ESCALA_ID' => $escExemplo,
                    'DETALHE_ESCALA_ID' => $detExemplo,
                    'FUNCIONARIO_ID' => $funcExemplo,
                    'COMPETENCIA' => $comp,
                    'MOTIVO_ALTERACAO_ID' => $mid,
                    'MOTIVO' => 'RETIFICACAO_FOLHA_SISFOLHA_FASE2',
                    'FASE' => '2',
                    'MENSAGEM' => 'Injeção stress: alteração retroativa simulada; teia de auditoria TCE/MDE (homolog).',
                    'DADOS_ANTIGOS_TURNO' => 'I',
                    'DADOS_NOVOS_TURNO' => 'M',
                    'TURNO_ID_ALVO' => $turnoM,
                    'ORIGEM' => 'SISFOLHA_MIGRACAO_STRESS',
                    'TIMESTAMP_MIG' => now()->toIso8601String(),
                ];
                $row = [];
                if ($col = $pick('ACAO', 'acao')) {
                    $row[$col] = 'ESCALA_MIGRACAO_RETROATIVA';
                }
                if ($col = $pick('TABELA', 'tabela')) {
                    $row[$col] = 'DETALHE_ESCALA_ITEM';
                }
                if ($col = $pick('DADOS_NOVOS', 'dados_novos', 'dados', 'contexto')) {
                    $row[$col] = json_encode($dados, JSON_UNESCAPED_UNICODE);
                } elseif ($col = $pick('DADOS_ANTIGOS', 'dados_antigos')) {
                    $row[$col] = json_encode($dados, JSON_UNESCAPED_UNICODE);
                }
                if ($col = $pick('USUARIO_ID', 'usuario_id', 'user_id')) {
                    $row[$col] = $userId;
                }
                if ($col = $pick('IP')) {
                    $row[$col] = '127.0.0.1';
                }
                if ($col = $pick('USER_AGENT', 'user_agent')) {
                    $row[$col] = 'SuperSeederEstresseMigracao/1.0';
                }
                if ($col = $pick('created_at', 'CREATED_AT', 'DATA_HORA')) {
                    $row[$col] = now()->subDays($h % 500)->subSeconds($h % 60);
                }
                if ($col = $pick('updated_at', 'UPDATED_AT')) {
                    $row[$col] = now()->subDays($h % 500);
                }
                if (! empty($row)) {
                    $rows[] = $row;
                }
            }
            if ($rows) {
                DB::table('AUDIT_LOG')->insert($rows);
            }
        }
    }
}
