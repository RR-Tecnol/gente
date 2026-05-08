<?php

namespace App\Console\Commands;

use App\Support\Scripts\UnificarUsuarios;
use Illuminate\Console\Command;

class GenteUnificarUsuariosCommand extends Command
{
    protected $signature = 'gente:unificar-usuarios {--dry-run : Apenas listar o que seria feito}';

    protected $description = 'Unifica USUARIO duplicados (FUNCIONARIO_ID / login) e concentra perfis no registro principal';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        if ($dry) {
            $this->warn('Modo --dry-run: nenhuma alteração será gravada.');
        }

        $r = UnificarUsuarios::run($dry);
        $this->info("Grupos com duplicidade: {$r['grupos']}");
        $this->info("Registros removidos (duplicados): {$r['removidos']}");

        foreach ($r['detalhe'] as $d) {
            if (isset($d['erro'])) {
                $this->error($d['erro']);
                continue;
            }
            $this->line('  Manter USUARIO_ID ' . $d['manter'] . ' (login: ' . ($d['login'] ?? '—') . '); removidos: ' . json_encode($d['removidos'] ?? []));
        }

        return self::SUCCESS;
    }
}
