<?php

namespace App\Console\Commands;

use App\Services\Import\SisfolhaImportOrchestrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class GenteImportSisfolha8aCommand extends Command
{
    protected $signature = 'gente:import-sisfolha-8a
        {acao : load|validate|apply — ingestão, pré-validação ou promoção}
        {--file= : Caminho do CSV (obrigatório para load)}
        {--run= : ID do run em sisfolha_import_runs (validate|apply)}
        {--operator= : USUARIO_ID do operador para AUDIT_LOG (opcional)}
        {--chunk= : Sobrepõe GENTE_SISFOLHA_IMPORT_CHUNK no apply}';

    protected $description = 'Fase 8A: carga controlada SISFOLHA → staging → PESSOA/USUARIO/FUNCIONARIO/LOTACAO';

    public function handle(SisfolhaImportOrchestrator $orchestrator): int
    {
        if (! Schema::hasTable('sisfolha_import_runs')) {
            $this->error('Tabelas 8A em falta. Execute: php artisan migrate');

            return self::FAILURE;
        }

        $acao = strtolower(trim((string) $this->argument('acao')));
        $operatorOpt = $this->option('operator');
        $operatorUsuarioId = $operatorOpt !== null && $operatorOpt !== ''
            ? (int) $operatorOpt
            : (int) config('gente_sisfolha_import.operator_usuario_id', 0);

        if ($acao === 'load') {
            $file = (string) $this->option('file');
            if ($file === '') {
                $this->error('Use --file=/caminho/absoluto/para.csv');

                return self::FAILURE;
            }
            $path = $file;
            if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
                $path = base_path($path);
            }
            $runId = $orchestrator->loadCsvToStaging($path, $operatorUsuarioId > 0 ? $operatorUsuarioId : null);
            $this->info("Run #{$runId} criado (staging carregado).");

            return self::SUCCESS;
        }

        $runId = (int) $this->option('run');
        if ($runId <= 0) {
            $this->error('Indique --run=<id> (sisfolha_import_runs).');

            return self::FAILURE;
        }

        if ($acao === 'validate') {
            $stats = $orchestrator->validateRun($runId);
            $this->table(array_keys($stats), [array_values($stats)]);
            $this->info('Run marcado como validated (ver totais_json na tabela de runs).');

            return self::SUCCESS;
        }

        if ($acao === 'apply') {
            $chunk = $this->option('chunk');
            $chunkSize = $chunk !== null && $chunk !== '' ? (int) $chunk : null;
            $res = $orchestrator->applyRun($runId, $chunkSize, $operatorUsuarioId > 0 ? $operatorUsuarioId : null);
            $this->table(array_keys($res), [array_values($res)]);

            return self::SUCCESS;
        }

        $this->error('Ação inválida. Use: load | validate | apply');

        return self::FAILURE;
    }
}
