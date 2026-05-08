<?php

namespace App\Console\Commands;

use App\Jobs\ShadowCalcChunkJob;
use App\Jobs\ShadowDiffChunkJob;
use App\Jobs\ShadowIngestChunkJob;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Throwable;

class ShadowDispatchPipelinesCommand extends Command
{
    protected $signature = 'shadow:dispatch
        {competencia : Formato YYYY-MM}
        {--snapshot-dir=}
        {--chunk=}
        {--etapa=todas : etl|calc|diff|todas}
        {--limiar=}
        {--run-id= : Reusar RUN_ID existente (recomendado para etapa=diff com calc_db)}
        {--diff-source=snapshot : snapshot|calc_db}';

    protected $description = 'Dispara batches do pipeline shadow (ETL, cálculo, diff).';

    public function handle(): int
    {
        $competencia = (string) $this->argument('competencia');
        $snapshotDir = (string) ($this->option('snapshot-dir') ?: (config('shadow.snapshot_root') . DIRECTORY_SEPARATOR . $competencia));
        $chunkSize = (int) ($this->option('chunk') ?: config('shadow.chunk_size', 500));
        $etapa = (string) $this->option('etapa');
        $limiar = (string) ($this->option('limiar') ?: config('shadow.limiar_tolerancia_rs', '0.03'));
        $diffSource = (string) $this->option('diff-source');
        $runId = (string) ($this->option('run-id') ?: ('shadow-' . $competencia . '-' . now()->format('YmdHis')));
        $runExists = DB::table('SHADOW_RUN')->where('RUN_ID', $runId)->exists();

        if (!in_array($etapa, ['etl', 'calc', 'diff', 'todas'], true)) {
            $this->error('Etapa inválida. Use: etl, calc, diff ou todas.');
            return self::FAILURE;
        }
        if (!in_array($diffSource, ['snapshot', 'calc_db'], true)) {
            $this->error('diff-source inválido. Use: snapshot ou calc_db.');
            return self::FAILURE;
        }
        if (($etapa === 'diff' || $etapa === 'todas') && !extension_loaded('bcmath')) {
            $this->error('Extensão BCMath não carregada. Instale/ative bcmath para executar diff matemático com segurança.');
            return self::FAILURE;
        }

        if (!is_file($snapshotDir . DIRECTORY_SEPARATOR . 'servidores.csv')) {
            $this->error('Arquivo servidores.csv não encontrado em: ' . $snapshotDir);
            return self::FAILURE;
        }

        if (($etapa === 'diff' || $etapa === 'todas') && !is_file($snapshotDir . DIRECTORY_SEPARATOR . 'resultado_legado.csv')) {
            $this->error('Arquivo resultado_legado.csv não encontrado em: ' . $snapshotDir);
            return self::FAILURE;
        }

        if (($etapa === 'diff' || $etapa === 'todas') && $diffSource === 'snapshot' && !is_file($snapshotDir . DIRECTORY_SEPARATOR . 'resultado_gente.csv')) {
            $this->error('Arquivo resultado_gente.csv não encontrado em: ' . $snapshotDir);
            return self::FAILURE;
        }
        $rubricaLeg = $snapshotDir . DIRECTORY_SEPARATOR . 'rubricas_legado.csv';
        $rubricaGente = $snapshotDir . DIRECTORY_SEPARATOR . 'rubricas_gente.csv';
        if (($etapa === 'diff' || $etapa === 'todas') && (is_file($rubricaLeg) xor is_file($rubricaGente))) {
            $this->error('Forneça rubricas_legado.csv e rubricas_gente.csv em conjunto, ou omita ambos. Diretório: ' . $snapshotDir);
            return self::FAILURE;
        }
        if (($etapa === 'diff' || $etapa === 'todas') && $diffSource === 'calc_db') {
            $calcRows = DB::table('SHADOW_RESULTADO_CALC')->where('RUN_ID', $runId)->count();
            if ($calcRows === 0) {
                $this->error('diff-source=calc_db exige SHADOW_RESULTADO_CALC preenchido para este RUN_ID. Execute etapa calc antes.');
                return self::FAILURE;
            }
        }

        if (!$runExists) {
            DB::table('SHADOW_RUN')->insert([
                'RUN_ID' => $runId,
                'COMPETENCIA' => $competencia,
                'SNAPSHOT_DIR' => $snapshotDir,
                'SNAPSHOT_SHA256' => $this->snapshotSha($snapshotDir),
                'STATUS' => 'em_execucao',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $servidores = $this->readCsv($snapshotDir . DIRECTORY_SEPARATOR . 'servidores.csv');
        $servidorChunks = array_chunk($servidores, max(1, $chunkSize));

        if ($etapa === 'etl' || $etapa === 'todas') {
            $jobs = [];
            foreach ($servidorChunks as $chunk) {
                $jobs[] = (new ShadowIngestChunkJob($runId, $competencia, $this->snapshotSha($snapshotDir), $chunk))
                    ->onQueue((string) config('shadow.queues.etl'));
            }
            $this->dispatchBatch($jobs, 'etl', $runId);
        }

        if ($etapa === 'calc' || $etapa === 'todas') {
            $jobs = [];
            foreach ($servidorChunks as $chunk) {
                $jobs[] = (new ShadowCalcChunkJob($runId, $competencia, $chunk))
                    ->onQueue((string) config('shadow.queues.calc'));
            }
            $this->dispatchBatch($jobs, 'calc', $runId);
        }

        if ($etapa === 'diff' || $etapa === 'todas') {
            $legacy = $this->indexByCpf($this->readCsv($snapshotDir . DIRECTORY_SEPARATOR . 'resultado_legado.csv'));
            $gente = $diffSource === 'calc_db'
                ? $this->loadCalcDbRows($runId)
                : $this->indexByCpf($this->readCsv($snapshotDir . DIRECTORY_SEPARATOR . 'resultado_gente.csv'));
            $diffRows = $this->mergeDiffRows($legacy, $gente);
            if ($diffSource === 'snapshot' && is_file($rubricaLeg) && is_file($rubricaGente)) {
                $diffRows = array_merge(
                    $diffRows,
                    $this->mergeRubricaDiffRows(
                        $this->indexRubricaChave($this->readCsv($rubricaLeg)),
                        $this->indexRubricaChave($this->readCsv($rubricaGente))
                    )
                );
            }
            $diffChunks = array_chunk($diffRows, max(1, $chunkSize));

            $jobs = [];
            foreach ($diffChunks as $chunk) {
                $jobs[] = (new ShadowDiffChunkJob($runId, $competencia, $limiar, $chunk))
                    ->onQueue((string) config('shadow.queues.diff'));
            }
            $this->dispatchBatch($jobs, 'diff', $runId);
        }

        DB::table('SHADOW_RUN')
            ->where('RUN_ID', $runId)
            ->update([
                'TOTAL_SERVIDORES' => count($servidores),
                'STATUS' => 'batches_despachados',
                'updated_at' => now(),
            ]);

        $this->info('Pipeline shadow despachado com sucesso. RUN_ID=' . $runId);
        return self::SUCCESS;
    }

    private function dispatchBatch(array $jobs, string $name, string $runId): void
    {
        Bus::batch($jobs)
            ->name("shadow:{$name}:{$runId}")
            ->allowFailures()
            ->then(function (Batch $batch): void {
                if ($batch->hasFailures()) {
                    return;
                }
            })
            ->catch(function (Batch $batch, Throwable $e): void {
                report($e);
            })
            ->dispatch();
    }

    private function snapshotSha(string $snapshotDir): string
    {
        $files = glob($snapshotDir . DIRECTORY_SEPARATOR . '*.csv') ?: [];
        sort($files);
        $ctx = hash_init('sha256');
        foreach ($files as $file) {
            hash_update_file($ctx, $file);
        }
        return hash_final($ctx);
    }

    private function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return $rows;
        }

        $headers = fgetcsv($handle, 0, ';');
        if (!$headers) {
            fclose($handle);
            return $rows;
        }

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $row = [];
            foreach ($headers as $i => $header) {
                $row[strtolower(trim((string) $header))] = $data[$i] ?? null;
            }
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    private function indexByCpf(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $cpf = (string) ($row['cpf'] ?? '');
            if ($cpf !== '') {
                $indexed[$cpf] = $row;
            }
        }
        return $indexed;
    }

    private function mergeDiffRows(array $legacy, array $gente): array
    {
        $cpfs = array_unique(array_merge(array_keys($legacy), array_keys($gente)));
        $rows = [];

        foreach ($cpfs as $cpf) {
            $l = $legacy[$cpf] ?? [];
            $g = $gente[$cpf] ?? [];
            $rows[] = [
                'cpf' => $cpf,
                'matricula' => $g['matricula'] ?? ($l['matricula'] ?? null),
                'servidor_key' => $g['servidor_key'] ?? ($l['servidor_key'] ?? $cpf),
                'legacy' => $l['valor_liquido'] ?? '0',
                'novo' => $g['valor_liquido'] ?? '0',
                'agregacao' => 'liquido',
                'rubrica_codigo' => null,
                'rubrica_tipo' => null,
                'justificavel' => false,
                'justificativa' => null,
            ];
        }

        return $rows;
    }

    private function indexRubricaChave(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $cpf = (string) ($row['cpf'] ?? '');
            $cod = (string) ($row['rubrica_codigo'] ?? '');
            $tipo = (string) ($row['rubrica_tipo'] ?? '');
            if ($cpf === '' || $cod === '') {
                continue;
            }
            $key = $cpf . '|' . $cod . '|' . $tipo;
            $indexed[$key] = $row;
        }

        return $indexed;
    }

    private function mergeRubricaDiffRows(array $legacy, array $gente): array
    {
        $keys = array_unique(array_merge(array_keys($legacy), array_keys($gente)));
        $rows = [];
        foreach ($keys as $key) {
            $l = $legacy[$key] ?? [];
            $g = $gente[$key] ?? [];
            $partes = explode('|', (string) $key, 3);
            $cpf = (string) ($partes[0] ?? '');
            $rubricaCod = (string) ($partes[1] ?? '');
            $rubricaTipo = (string) ($partes[2] ?? '');

            $rows[] = [
                'cpf' => $cpf,
                'matricula' => $g['matricula'] ?? ($l['matricula'] ?? null),
                'servidor_key' => $g['servidor_key'] ?? ($l['servidor_key'] ?? $cpf),
                'legacy' => (string) ($l['valor'] ?? '0'),
                'novo' => (string) ($g['valor'] ?? '0'),
                'rubrica_codigo' => $rubricaCod,
                'rubrica_tipo' => $rubricaTipo,
                'agregacao' => 'rubrica',
                'justificavel' => false,
                'justificativa' => null,
            ];
        }

        return $rows;
    }

    private function loadCalcDbRows(string $runId): array
    {
        $rows = DB::table('SHADOW_RESULTADO_CALC')
            ->where('RUN_ID', $runId)
            ->select([
                'CPF as cpf',
                'MATRICULA as matricula',
                'CPF as servidor_key',
                'VALOR_LIQUIDO as valor_liquido',
            ])
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return $this->indexByCpf($rows);
    }
}

