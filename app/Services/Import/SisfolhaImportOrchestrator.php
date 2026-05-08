<?php

namespace App\Services\Import;

use App\Support\GenteAuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Fase 8A — Validação e promoção staging → PESSOA / USUARIO / FUNCIONARIO / LOTACAO.
 */
final class SisfolhaImportOrchestrator
{
    public function loadCsvToStaging(string $absolutePath, ?int $operatorUsuarioId): int
    {
        if (! is_readable($absolutePath)) {
            throw new \InvalidArgumentException('Ficheiro ilegível: '.$absolutePath);
        }

        $checksum = hash_file('sha256', $absolutePath) ?: null;
        $runId = (int) DB::table('sisfolha_import_runs')->insertGetId([
            'file_name' => basename($absolutePath),
            'file_checksum' => $checksum,
            'operator_usuario_id' => $operatorUsuarioId,
            'status' => 'loaded',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fh = fopen($absolutePath, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('Não foi possível abrir o CSV.');
        }
        $header = fgetcsv($fh);
        if ($header === false) {
            fclose($fh);
            throw new \RuntimeException('CSV vazio.');
        }
        $map = [];
        foreach ($header as $i => $h) {
            $map[strtolower(trim((string) $h))] = $i;
        }
        $need = ['cpf', 'matricula', 'nome', 'setor_codigo'];
        foreach ($need as $col) {
            if (! isset($map[$col])) {
                fclose($fh);
                throw new \InvalidArgumentException('CSV sem coluna obrigatória: '.$col);
            }
        }

        $line = 1;
        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            if ($this->csvRowVazio($row)) {
                continue;
            }
            $cpf = $this->onlyDigits($row[$map['cpf']] ?? '');
            $mat = trim((string) ($row[$map['matricula']] ?? ''));
            $nome = trim((string) ($row[$map['nome']] ?? ''));
            $setorCod = trim((string) ($row[$map['setor_codigo']] ?? ''));
            $cargoCod = isset($map['cargo_codigo']) ? trim((string) ($row[$map['cargo_codigo']] ?? '')) : '';
            $pis = isset($map['pis']) ? $this->onlyDigits($row[$map['pis']] ?? '') : '';

            DB::table('sisfolha_import_stg_rows')->insert([
                'run_id' => $runId,
                'line_number' => $line,
                'cpf_norm' => $cpf,
                'matricula_norm' => $mat,
                'nome' => $nome !== '' ? $nome : null,
                'setor_codigo_externo' => $setorCod !== '' ? $setorCod : null,
                'cargo_codigo_sisfolha' => $cargoCod !== '' ? $cargoCod : null,
                'pis_norm' => $pis !== '' ? $pis : null,
                'payload_json' => json_encode($row, JSON_UNESCAPED_UNICODE),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        fclose($fh);

        DB::table('sisfolha_import_runs')->where('id', $runId)->update([
            'status' => 'loaded',
            'finished_at' => now(),
            'updated_at' => now(),
        ]);

        return $runId;
    }

    /**
     * @return array<string, int>
     */
    public function validateRun(int $runId): array
    {
        $quarentenaId = SisfolhaQuarantineResolver::resolveSetorId();
        $matrCounts = DB::table('sisfolha_import_stg_rows')
            ->where('run_id', $runId)
            ->selectRaw('matricula_norm, COUNT(*) as c')
            ->groupBy('matricula_norm')
            ->pluck('c', 'matricula_norm')
            ->all();

        $stats = ['total' => 0, 'valid' => 0, 'invalid' => 0, 'quarentena' => 0];

        $rows = DB::table('sisfolha_import_stg_rows')->where('run_id', $runId)->orderBy('id')->get();
        foreach ($rows as $r) {
            $stats['total']++;
            $motivos = [];
            $cpf = (string) $r->cpf_norm;
            if (strlen($cpf) !== 11) {
                $motivos[] = 'CPF inválido';
            }
            $mat = (string) $r->matricula_norm;
            if ($mat === '') {
                $motivos[] = 'Matrícula vazia';
            }
            if (($matrCounts[$mat] ?? 0) > 1) {
                $motivos[] = 'Matrícula duplicada no ficheiro';
            }
            if (DB::table('FUNCIONARIO')->where('FUNCIONARIO_MATRICULA', $mat)->exists()) {
                $motivos[] = 'Matrícula já existe em FUNCIONARIO';
            }

            $setorDestino = $this->resolverSetorIdSomente((string) ($r->setor_codigo_externo ?? ''), $quarentenaId);
            if ($setorDestino === $quarentenaId && trim((string) ($r->setor_codigo_externo ?? '')) !== '') {
                $stats['quarentena']++;
            }

            $avisos = [];
            $cargoCod = trim((string) ($r->cargo_codigo_sisfolha ?? ''));
            if ($cargoCod !== '' && Schema::hasTable('sisfolha_cargo_depara')
                && ! DB::table('sisfolha_cargo_depara')->where('codigo_sisfolha', $cargoCod)->where('ativo', 1)->exists()) {
                $avisos[] = 'cargo_codigo sem de-para em sisfolha_cargo_depara (CARGO_ID ficará vazio na promoção)';
            }

            if ($motivos !== []) {
                $stats['invalid']++;
                DB::table('sisfolha_import_stg_rows')->where('id', $r->id)->update([
                    'status' => 'invalid',
                    'motivo' => implode('; ', $motivos),
                    'updated_at' => now(),
                ]);
            } else {
                $stats['valid']++;
                $motivoLinha = $avisos !== [] ? implode('; ', $avisos) : null;
                DB::table('sisfolha_import_stg_rows')->where('id', $r->id)->update([
                    'status' => 'valid',
                    'motivo' => $motivoLinha,
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('sisfolha_import_runs')->where('id', $runId)->update([
            'status' => 'validated',
            'totais_json' => json_encode($stats, JSON_UNESCAPED_UNICODE),
            'finished_at' => now(),
            'updated_at' => now(),
        ]);

        return $stats;
    }

    /**
     * @return array<string, int|string>
     */
    public function applyRun(int $runId, ?int $chunkSize, ?int $operatorUsuarioId): array
    {
        $chunkSize = $chunkSize ?? (int) config('gente_sisfolha_import.chunk_size', 250);
        $operatorUsuarioId = $operatorUsuarioId ?? (int) config('gente_sisfolha_import.operator_usuario_id', 0);
        $quarentenaId = SisfolhaQuarantineResolver::resolveSetorId();

        $pendentes = DB::table('sisfolha_import_stg_rows')
            ->where('run_id', $runId)
            ->where('status', 'valid')
            ->orderBy('id')
            ->get();

        $applied = 0;
        $errors = 0;
        $chunks = $pendentes->chunk($chunkSize);
        $chunkIdx = 0;
        foreach ($chunks as $chunk) {
            $chunkIdx++;
            try {
                DB::transaction(function () use ($chunk, $quarentenaId, &$applied, $runId, $chunkIdx, $operatorUsuarioId) {
                    $usuarioAfetados = [];
                    foreach ($chunk as $r) {
                        $uid = $this->promoverLinha($r, $quarentenaId);
                        if ($uid > 0) {
                            $usuarioAfetados[$uid] = true;
                        }
                        $applied++;
                    }
                    foreach (array_keys($usuarioAfetados) as $uid) {
                        $minFid = (int) (DB::table('FUNCIONARIO')->where('USUARIO_ID', (int) $uid)->min('FUNCIONARIO_ID') ?? 0);
                        if ($minFid > 0 && Schema::hasColumn('USUARIO', 'FUNCIONARIO_ID')) {
                            DB::table('USUARIO')->where('USUARIO_ID', (int) $uid)->update(['FUNCIONARIO_ID' => $minFid]);
                        }
                    }
                });
                $this->auditChunk($runId, $chunkIdx, $chunk->count(), $applied, $operatorUsuarioId);
            } catch (\Throwable $e) {
                $errors++;
                foreach ($chunk as $r) {
                    DB::table('sisfolha_import_stg_rows')->where('id', $r->id)->update([
                        'status' => 'error',
                        'motivo' => 'Exceção: '.$e->getMessage(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->auditRunFinal($runId, $applied, $errors, $operatorUsuarioId);

        DB::table('sisfolha_import_runs')->where('id', $runId)->update([
            'status' => 'applied',
            'totais_json' => json_encode(['applied' => $applied, 'errors' => $errors], JSON_UNESCAPED_UNICODE),
            'finished_at' => now(),
            'updated_at' => now(),
        ]);

        return ['applied' => $applied, 'errors' => $errors];
    }

    /**
     * @return int USUARIO_ID afetado (para sincronizar FUNCIONARIO_ID principal)
     */
    private function promoverLinha(object $r, int $quarentenaId): int
    {
        $cpf = (string) $r->cpf_norm;
        $mat = (string) $r->matricula_norm;
        $nome = (string) ($r->nome ?? 'Importado SISFOLHA');

        $setorId = $this->resolverSetorIdSomente((string) ($r->setor_codigo_externo ?? ''), $quarentenaId);

        $pessoaId = $this->upsertPessoa($cpf, $nome, (string) ($r->pis_norm ?? ''));
        $usuarioId = $this->ensureUsuarioPorCpf($cpf, $nome);
        if (Schema::hasColumn('FUNCIONARIO', 'USUARIO_ID')) {
            DB::table('FUNCIONARIO')->where('PESSOA_ID', $pessoaId)->update(['USUARIO_ID' => $usuarioId]);
        }

        $cargoId = $this->resolverCargoId((string) ($r->cargo_codigo_sisfolha ?? ''));

        $funcData = [
            'PESSOA_ID' => $pessoaId,
            'FUNCIONARIO_MATRICULA' => $mat,
            'FUNCIONARIO_DATA_INICIO' => now()->toDateString(),
            'USUARIO_ID' => $usuarioId,
        ];
        if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_ATIVO')) {
            $funcData['FUNCIONARIO_ATIVO'] = 1;
        }
        if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_CADASTRO')) {
            $funcData['FUNCIONARIO_DATA_CADASTRO'] = now()->toDateString();
        }
        if ($cargoId !== null && Schema::hasColumn('FUNCIONARIO', 'CARGO_ID')) {
            $funcData['CARGO_ID'] = $cargoId;
        }
        $vinId = $this->primeiroVinculoId();
        if ($vinId !== null && Schema::hasColumn('FUNCIONARIO', 'VINCULO_ID')) {
            $funcData['VINCULO_ID'] = $vinId;
        }

        $funcionarioId = (int) DB::table('FUNCIONARIO')->insertGetId($funcData);

        $lotRow = [
            'FUNCIONARIO_ID' => $funcionarioId,
            'SETOR_ID' => $setorId,
        ];
        if (Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_INICIO')) {
            $lotRow['LOTACAO_DATA_INICIO'] = now()->toDateString();
        }
        if ($vinId !== null && Schema::hasColumn('LOTACAO', 'VINCULO_ID')) {
            $lotRow['VINCULO_ID'] = $vinId;
        }
        $lotacaoId = (int) DB::table('LOTACAO')->insertGetId($lotRow);

        DB::table('sisfolha_import_stg_rows')->where('id', $r->id)->update([
            'status' => 'applied',
            'promoted_pessoa_id' => $pessoaId,
            'promoted_usuario_id' => $usuarioId,
            'promoted_funcionario_id' => $funcionarioId,
            'promoted_lotacao_id' => $lotacaoId,
            'motivo' => null,
            'updated_at' => now(),
        ]);

        return $usuarioId;
    }

    private function upsertPessoa(string $cpf, string $nome, string $pis): int
    {
        $cpfCol = Schema::hasColumn('PESSOA', 'PESSOA_CPF_NUMERO') ? 'PESSOA_CPF_NUMERO' : 'PESSOA_CPF';
        $existing = (int) (DB::table('PESSOA')->where($cpfCol, $cpf)->value('PESSOA_ID') ?? 0);
        if ($existing > 0) {
            $upd = ['PESSOA_NOME' => Str::upper($nome)];
            if ($pis !== '' && Schema::hasColumn('PESSOA', 'PESSOA_PIS_PASEP')) {
                $upd['PESSOA_PIS_PASEP'] = $pis;
            }
            DB::table('PESSOA')->where('PESSOA_ID', $existing)->update($upd);

            return $existing;
        }

        $row = ['PESSOA_NOME' => Str::upper($nome), $cpfCol => $cpf];
        if (Schema::hasColumn('PESSOA', 'PESSOA_CPF') && $cpfCol !== 'PESSOA_CPF') {
            $row['PESSOA_CPF'] = $cpf;
        }
        if ($pis !== '' && Schema::hasColumn('PESSOA', 'PESSOA_PIS_PASEP')) {
            $row['PESSOA_PIS_PASEP'] = $pis;
        }
        if (Schema::hasColumn('PESSOA', 'PESSOA_ATIVO')) {
            $row['PESSOA_ATIVO'] = 1;
        }
        if (Schema::hasColumn('PESSOA', 'PESSOA_DATA_CADASTRO')) {
            $row['PESSOA_DATA_CADASTRO'] = now()->toDateString();
        }
        if (Schema::hasColumn('PESSOA', 'created_at')) {
            $row['created_at'] = now();
            $row['updated_at'] = now();
        }

        return (int) DB::table('PESSOA')->insertGetId($row);
    }

    private function ensureUsuarioPorCpf(string $cpf, string $nome): int
    {
        $login = $cpf;
        $existing = (int) (DB::table('USUARIO')->where('USUARIO_LOGIN', $login)->value('USUARIO_ID') ?? 0);
        if ($existing > 0) {
            $upd = ['USUARIO_NOME' => $nome];
            if (Schema::hasColumn('USUARIO', 'USUARIO_CPF')) {
                $upd['USUARIO_CPF'] = $cpf;
            }
            if ($upd !== []) {
                DB::table('USUARIO')->where('USUARIO_ID', $existing)->update($upd);
            }

            return $existing;
        }

        $hash = (string) config('gente_sisfolha_import.default_password_hash', '');
        if ($hash === '') {
            $hash = bcrypt(Str::random(32));
        }

        $u = [
            'USUARIO_LOGIN' => $login,
            'USUARIO_SENHA' => $hash,
            'USUARIO_NOME' => $nome,
            'USUARIO_ATIVO' => 1,
            'USUARIO_PRIMEIRO_ACESSO' => 1,
            'USUARIO_ALTERAR_SENHA' => 1,
        ];
        if (Schema::hasColumn('USUARIO', 'USUARIO_CPF')) {
            $u['USUARIO_CPF'] = $cpf;
        }
        if (Schema::hasColumn('USUARIO', 'created_at')) {
            $u['created_at'] = now();
            $u['updated_at'] = now();
        }

        $newId = (int) DB::table('USUARIO')->insertGetId($u);

        if (Schema::hasTable('USUARIO_PERFIL')) {
            $upCols = Schema::getColumnListing('USUARIO_PERFIL');
            $perfilId = (int) (DB::table('PERFIL')
                ->whereIn('PERFIL_NOME', ['Funcionario', 'Funcionário', 'Externo'])
                ->value('PERFIL_ID') ?? 5);
            $existsPerfil = DB::table('USUARIO_PERFIL')
                ->where('USUARIO_ID', $newId)
                ->where('PERFIL_ID', $perfilId)
                ->exists();
            if (! $existsPerfil) {
                $payloadUp = [
                    'USUARIO_ID' => $newId,
                    'PERFIL_ID' => $perfilId,
                    'USUARIO_PERFIL_ATIVO' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $payloadUp = array_intersect_key($payloadUp, array_flip($upCols));
                if ($payloadUp !== []) {
                    DB::table('USUARIO_PERFIL')->insert($payloadUp);
                }
            }
        }

        return $newId;
    }

    private function resolverCargoId(string $codigo): ?int
    {
        if ($codigo === '' || ! Schema::hasTable('sisfolha_cargo_depara')) {
            return null;
        }
        $id = (int) (DB::table('sisfolha_cargo_depara')
            ->where('codigo_sisfolha', $codigo)
            ->where('ativo', 1)
            ->value('cargo_id') ?? 0);

        return $id > 0 ? $id : null;
    }

    private function primeiroVinculoId(): ?int
    {
        if (! Schema::hasTable('VINCULO')) {
            return null;
        }

        $id = DB::table('VINCULO')->orderBy('VINCULO_ID')->value('VINCULO_ID');

        return $id ? (int) $id : null;
    }

    private function resolverSetorIdSomente(string $setorCodigo, int $quarentenaId): int
    {
        $setorCodigo = trim($setorCodigo);
        if ($setorCodigo === '') {
            return $quarentenaId;
        }
        if (ctype_digit($setorCodigo)) {
            $sid = (int) $setorCodigo;
            $row = DB::table('SETOR')->where('SETOR_ID', $sid)->first(['UNIDADE_ID']);
            if ($row && (int) ($row->UNIDADE_ID ?? 0) > 0) {
                return $sid;
            }
        }

        return $quarentenaId;
    }

    private function auditRunFinal(int $runId, int $applied, int $errors, int $operatorUsuarioId): void
    {
        $this->auditEvent('CARGA_MESTRA_SISFOLHA_RUN', [
            'run_id' => $runId,
            'applied' => $applied,
            'errors' => $errors,
            'fase' => '8a',
        ], $operatorUsuarioId);
    }

    private function auditChunk(int $runId, int $chunkIdx, int $chunkCount, int $appliedSoFar, int $operatorUsuarioId): void
    {
        $this->auditEvent('CARGA_MESTRA_SISFOLHA_CHUNK', [
            'run_id' => $runId,
            'chunk_index' => $chunkIdx,
            'chunk_rows' => $chunkCount,
            'applied_so_far' => $appliedSoFar,
        ], $operatorUsuarioId);
    }

    private function auditEvent(string $acao, array $payload, int $operatorUsuarioId): void
    {
        if ($operatorUsuarioId <= 0 || ! Schema::hasTable('AUDIT_LOG')) {
            return;
        }
        $cols = Schema::getColumnListing('AUDIT_LOG');
        $byLower = [];
        foreach ($cols as $c) {
            $byLower[strtolower($c)] = $c;
        }
        $pick = function (string ...$candidates) use ($byLower): ?string {
            foreach ($candidates as $name) {
                $k = $byLower[strtolower($name)] ?? null;
                if ($k !== null) {
                    return $k;
                }
            }

            return null;
        };
        $row = [];
        if ($c = $pick('ACAO', 'acao')) {
            $row[$c] = $acao;
        }
        if ($c = $pick('TABELA', 'tabela')) {
            $row[$c] = 'sisfolha_import_runs';
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($c = $pick('DADOS_NOVOS', 'dados_novos', 'contexto', 'context')) {
            $row[$c] = $json;
        }
        if ($c = $pick('USUARIO_ID', 'usuario_id')) {
            $row[$c] = $operatorUsuarioId;
        }
        if ($row === []) {
            return;
        }
        try {
            GenteAuditWriter::insertChainedRow($row);
        } catch (\Throwable) {
            // não bloquear import se canal de auditoria falhar
        }
    }

    private function onlyDigits(?string $s): string
    {
        return preg_replace('/\D+/', '', (string) $s) ?? '';
    }

    private function csvRowVazio(array $row): bool
    {
        foreach ($row as $c) {
            if (trim((string) $c) !== '') {
                return false;
            }
        }

        return true;
    }
}
