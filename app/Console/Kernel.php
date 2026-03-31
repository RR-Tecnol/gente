<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // SEC-PROD-03: Limpeza diária automática
        $schedule->call(function () {
            \Illuminate\Support\Facades\DB::table('LOGIN_ATTEMPTS')->where('TENTATIVA_EM', '<', now()->subDay())->delete();
        })->daily();

        // Arquivamento mensal do security.log (1º dia de cada mês às 02:00)
        $schedule->call(function () {
            $logPath     = storage_path('logs/security.log');
            $arquivoDir  = storage_path('logs/security/arquivo');
            $mesAnterior = now()->subMonth()->format('Y-m');
            $destino     = "$arquivoDir/security-$mesAnterior.log.gz";

            if (!file_exists($logPath)) return;
            if (!is_dir($arquivoDir)) mkdir($arquivoDir, 0755, true);

            // Comprimir e mover
            $conteudo = file_get_contents($logPath);
            $gz = gzopen($destino, 'w9');
            gzwrite($gz, $conteudo);
            gzclose($gz);

            // Apagar o original só após confirmar que o .gz foi criado com sucesso
            if (file_exists($destino) && filesize($destino) > 0) {
                file_put_contents($logPath, ''); // truncar (não apagar) — mantém o handle aberto do Laravel
                \Illuminate\Support\Facades\Log::channel('security')
                    ->info('log_arquivado', ['arquivo' => $destino, 'mes' => $mesAnterior]);
            }
        })->monthlyOn(1, '02:00')->name('security-log-arquivamento')->withoutOverlapping();

        // Manutenção: alertar se arquivo .gz corrompido (verificação trimestral)
        $schedule->call(function () {
            $arquivoDir = storage_path('logs/security/arquivo');
            if (!is_dir($arquivoDir)) return;
            foreach (glob("$arquivoDir/*.gz") as $gz) {
                $handle = @gzopen($gz, 'r');
                if (!$handle) {
                    \Illuminate\Support\Facades\Log::channel('security')
                        ->error('log_arquivo_corrompido', ['arquivo' => $gz]);
                } else {
                    gzclose($handle);
                }
            }
        })->quarterly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
