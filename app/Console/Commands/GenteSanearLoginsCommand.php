<?php

namespace App\Console\Commands;

use App\Support\Scripts\SanearLoginsLegados;
use Illuminate\Console\Command;

class GenteSanearLoginsCommand extends Command
{
    protected $signature = 'gente:sanear-logins {--dry-run : Apenas simula, sem gravar}';

    protected $description = 'Saneia USUARIO_LOGIN e USUARIO_EMAIL legados (trim/lowercase para e-mail)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Modo --dry-run: nenhuma alteração será gravada.');
        }

        $r = SanearLoginsLegados::run($dryRun);
        $this->info("Total analisado: {$r['total']}");
        $this->info("Registros corrigidos: {$r['corrigidos']}");
        $this->line("USUARIO_LOGIN corrigidos: {$r['login_corrigidos']}");
        $this->line("USUARIO_EMAIL corrigidos: {$r['email_corrigidos']}");

        return self::SUCCESS;
    }
}

