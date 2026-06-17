<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ShadowSnapshotValidateCommand extends Command
{
    protected $signature = 'shadow:snapshot-validar {competencia : Formato YYYY-MM} {--snapshot-dir=}';

    protected $description = 'Valida artefatos mínimos do snapshot para execução shadow (P3).';

    public function handle(): int
    {
        $competencia = (string) $this->argument('competencia');
        $snapshotDir = (string) ($this->option('snapshot-dir') ?: (config('shadow.snapshot_root') . DIRECTORY_SEPARATOR . $competencia));

        $required = [
            'manifest.json',
            'servidores.csv',
            'resultado_legado.csv',
            'resultado_gente.csv',
        ];

        $missing = [];
        foreach ($required as $file) {
            if (!is_file($snapshotDir . DIRECTORY_SEPARATOR . $file)) {
                $missing[] = $file;
            }
        }

        if (!empty($missing)) {
            $this->error('Snapshot inválido. Arquivos ausentes: ' . implode(', ', $missing));
            return self::FAILURE;
        }

        $manifestRaw = file_get_contents($snapshotDir . DIRECTORY_SEPARATOR . 'manifest.json') ?: '';
        json_decode($manifestRaw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('manifest.json inválido: ' . json_last_error_msg());
            return self::FAILURE;
        }

        $this->info('Snapshot validado com sucesso: ' . $snapshotDir);
        return self::SUCCESS;
    }
}

