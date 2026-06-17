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
        \App\Console\Commands\AuditVerifyChain::class,
        \App\Console\Commands\GenteImportSisfolha8aCommand::class,
        \App\Console\Commands\GenteSmokeTeia7aCommand::class,
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

        // Frente 4: cópia de AUDIT_LOG (JSONL) para disco de custódia (local ou S3 com retenção no bucket)
        $schedule->call(function () {
            (new \App\Jobs\StreamAuditToSecureVault)->handle();
        })->everyTenMinutes()
            ->name('gente-audit-secure-vault')
            ->withoutOverlapping(8)
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('scheduler_fail', ['job' => 'gente-audit-secure-vault']);
            });

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

        // S3.4: rotina de inventário banco de horas (idempotente; sem mutação por defeito)
        $schedule->command('jornada:banco-horas-decaimento --dry-run')
            ->monthlyOn(1, '03:00')
            ->name('jornada-banco-horas-relatorio')
            ->withoutOverlapping(120);

        // S6.1: inicializa e processa prova de vida RPPS/IPAM mensalmente
        $schedule->command('rpps:prova-vida-processar --inicializar')
            ->monthlyOn(1, '04:00')
            ->name('rpps-prova-vida-processar')
            ->withoutOverlapping(120)
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('scheduler_ok', ['job' => 'rpps-prova-vida-processar']);
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('scheduler_fail', ['job' => 'rpps-prova-vida-processar']);
            });

        // S7 fase 1: fila eSocial com retry/backoff (slice frequente e curto)
        $schedule->command('esocial:processar-fila --limit=80')
            ->everyFiveMinutes()
            ->name('esocial-processar-fila')
            ->withoutOverlapping(10)
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('scheduler_ok', ['job' => 'esocial-processar-fila']);
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('scheduler_fail', ['job' => 'esocial-processar-fila']);
            });

        // Sentinela de Integridade: auto-auditoria contínua a cada 5 minutos.
        $schedule->command('gente:sentinela-run --json')
            ->everyFiveMinutes()
            ->name('gente-sentinela-integridade')
            ->withoutOverlapping(10)
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('scheduler_ok', ['job' => 'gente-sentinela-integridade']);
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('scheduler_fail', ['job' => 'gente-sentinela-integridade']);
            });

        // S9 fase 1: verificação operacional consolidada diária
        $schedule->command('gente:healthcheck --json')
            ->dailyAt('06:00')
            ->name('gente-healthcheck')
            ->withoutOverlapping(30)
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('scheduler_ok', ['job' => 'gente-healthcheck']);
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('scheduler_fail', ['job' => 'gente-healthcheck']);
            });
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
