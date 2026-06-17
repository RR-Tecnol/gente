<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ShadowExportRunCommand extends Command
{
    protected $signature = 'shadow:export-run
        {run_id : RUN_ID do SHADOW_RUN}
        {--out-dir= : Padrão: storage/app/shadow-exports/{run_id}}';

    protected $description = 'P3/§15.10: exporta diff_resultado.csv, diff_sumario.json e rascunho de ata_justificativas.md no diretório indicado.';

    public function handle(): int
    {
        $runId = (string) $this->argument('run_id');
        $base = (string) ($this->option('out-dir') ?: (storage_path('app/shadow-exports/' . $runId)));
        if (!is_dir($base) && !@mkdir($base, 0775, true) && !is_dir($base)) {
            $this->error('Não foi possível criar: ' . $base);
            return self::FAILURE;
        }

        $run = DB::table('SHADOW_RUN')->where('RUN_ID', $runId)->first();
        if (!$run) {
            $this->error('RUN_ID não encontrado: ' . $runId);
            return self::FAILURE;
        }

        $rows = DB::table('DIFF_RECONCILIACAO')
            ->where('RUN_ID', $runId)
            ->orderBy('DIFF_ID')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $csv = $base . DIRECTORY_SEPARATOR . 'diff_resultado.csv';
        $fh = fopen($csv, 'w');
        if ($fh === false) {
            $this->error('Falha ao abrir: ' . $csv);
            return self::FAILURE;
        }
        fputcsv($fh, [
            'RUN_ID', 'COMPETENCIA', 'CPF', 'MATRICULA', 'RUBRICA_CODIGO', 'RUBRICA_TIPO', 'AGREGACAO',
            'VALOR_LEGADO', 'VALOR_NOVO', 'DELTA_ABSOLUTO', 'CLASSIFICACAO', 'JUSTIFICADO', 'JUSTIFICATIVA',
        ], ';');
        foreach ($rows as $r) {
            fputcsv($fh, [
                $r['RUN_ID'] ?? '',
                $r['COMPETENCIA'] ?? '',
                $r['CPF'] ?? '',
                $r['MATRICULA'] ?? '',
                $r['RUBRICA_CODIGO'] ?? '',
                $r['RUBRICA_TIPO'] ?? '',
                $r['AGREGACAO'] ?? 'liquido',
                (string) ($r['VALOR_LEGADO'] ?? ''),
                (string) ($r['VALOR_NOVO'] ?? ''),
                (string) ($r['DELTA_ABSOLUTO'] ?? ''),
                $r['CLASSIFICACAO'] ?? '',
                (string) ($r['JUSTIFICADO'] ?? '0'),
                (string) ($r['JUSTIFICATIVA'] ?? ''),
            ], ';');
        }
        fclose($fh);

        $classes = DB::table('DIFF_RECONCILIACAO')
            ->where('RUN_ID', $runId)
            ->select('CLASSIFICACAO', DB::raw('COUNT(*) as qtd'))
            ->groupBy('CLASSIFICACAO')
            ->pluck('qtd', 'CLASSIFICACAO')
            ->toArray();
        $sumario = [
            'run_id' => $runId,
            'competencia' => $run->COMPETENCIA,
            'gerado_em' => now()->toIso8601String(),
            'totais_por_classificacao' => $classes,
            'total_linhas' => count($rows),
        ];
        file_put_contents(
            $base . DIRECTORY_SEPARATOR . 'diff_sumario.json',
            json_encode($sumario, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
        );

        $just = array_values(array_filter($rows, fn ($r) => ($r['CLASSIFICACAO'] ?? '') === 'DIVERGENCIA_JUSTIFICAVEL'));
        $ata = "# Ata de divergências justificáveis (rascunho) — run `{$runId}`\n\n";
        $ata .= 'Gerado em: ' . now()->format('Y-m-d H:i') . "\n\n";
        if ($just === []) {
            $ata .= "Nenhuma linha com classificação DIVERGENCIA_JUSTIFICAVEL.\n";
        } else {
            foreach ($just as $j) {
                $ata .= "- CPF: " . ($j['CPF'] ?? '') . "; rubrica: " . ($j['RUBRICA_CODIGO'] ?? '(líquido)') . "; delta: " . ($j['DELTA_ABSOLUTO'] ?? '') . "\n";
            }
        }
        $ata .= "\n_Este ficheiro é template operacional; assinaturas e pareceres institucionais ficam fora do escopo de automação._\n";
        file_put_contents($base . DIRECTORY_SEPARATOR . 'ata_justificativas.md', $ata);

        $this->info('Exportado em: ' . $base);
        return self::SUCCESS;
    }
}
