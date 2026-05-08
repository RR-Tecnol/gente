<?php

namespace App\Console\Commands;

use App\Services\Shadow\SnapshotManifestoCanonicoService;
use Illuminate\Console\Command;

class ShadowSnapshotCanonicoValidarCommand extends Command
{
    protected $signature = 'shadow:snapshot-canonico-validar
        {competencia : Formato YYYY-MM}
        {--snapshot-dir= : Diretório do snapshot; default: storage/app/shadow/YYYY-MM}
        {--json : Saída em JSON}';

    protected $description = 'P3/§15: valida manifest.json, hashes e contagens (snapshot canónico)';

    public function handle(SnapshotManifestoCanonicoService $validador): int
    {
        $competencia = (string) $this->argument('competencia');
        $dir = (string) ($this->option('snapshot-dir') ?: (config('shadow.snapshot_root') . DIRECTORY_SEPARATOR . $competencia));
        $result = $validador->validar($dir);

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'ok' => $result['ok'],
                'snapshot_dir' => $dir,
                'erros' => $result['erros'],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            if ($result['ok']) {
                $this->info('Snapshot canónico validado: ' . $dir);
            } else {
                $this->error('Falha na validação canónica:');
                foreach ($result['erros'] as $e) {
                    $this->line('- ' . $e);
                }
            }
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
