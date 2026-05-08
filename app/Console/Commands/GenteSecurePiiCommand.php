<?php

namespace App\Console\Commands;

use App\Support\PiiBlindIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reindexa blind index (HMAC) e, opcionalmente, cifra PESSOA_CPF_NUMERO (FLE).
 * Executar após migrar: php artisan migrate
 */
class GenteSecurePiiCommand extends Command
{
    protected $signature = 'gente:secure-pii
                            {--chunk=500 : Tamanho do lote}
                            {--fle : Cifrar coluna CPF (exige GENTE_PII_CPF_ENCRYPTED=true após carga)}';

    protected $description = 'Atualiza PESSOA_CPF_HASH (e opcionalmente cifra CPF) para conformidade PII';

    public function handle(): int
    {
        if (! Schema::hasTable('PESSOA') || ! Schema::hasColumn('PESSOA', 'PESSOA_CPF_HASH')) {
            $this->error('Tabela PESSOA ou coluna PESSOA_CPF_HASH inexistente — rode as migrations.');

            return 1;
        }
        if ((string) config('gente.pii.blind_salt', '') === '') {
            $this->warn('GENTE_PII_BLIND_SALT vazio: o HMAC usa APP_KEY. Para produção, defina salt dedicado (rotação independente de APP_KEY).');
        }

        $cpfCol = 'PESSOA_CPF_NUMERO';
        if (! Schema::hasColumn('PESSOA', $cpfCol)) {
            $cpfCol = Schema::hasColumn('PESSOA', 'PESSOA_CPF') ? 'PESSOA_CPF' : null;
        }
        if (! $cpfCol) {
            $this->error('Nenhuma coluna de CPF encontrada em PESSOA.');

            return 1;
        }

        $chunk = max(50, (int) $this->option('chunk'));
        $doFle = (bool) $this->option('fle');
        if ($doFle && ! config('gente.pii.cpf_field_encrypted', false)) {
            $this->warn('A opção --fle preconiza GENTE_PII_CPF_ENCRYPTED=true. A cifra será escrita; ative a flag após validar um lote em homolog.');
        }

        $n = 0;
        $lastId = 0;

        DB::table('PESSOA')
            ->orderBy('PESSOA_ID')
            ->where('PESSOA_ID', '>', 0)
            ->chunkById($chunk, function ($rows) use ($cpfCol, $doFle, &$n, &$lastId) {
                DB::transaction(function () use ($rows, $cpfCol, $doFle, &$n, &$lastId) {
                    foreach ($rows as $row) {
                        $id = (int) $row->PESSOA_ID;
                        $lastId = $id;
                        $raw = $row->{$cpfCol} ?? null;
                        if ($raw === null || $raw === '') {
                            DB::table('PESSOA')->where('PESSOA_ID', $id)->update(['PESSOA_CPF_HASH' => null]);
                            $n++;

                            continue;
                        }
                        $str = (string) $raw;
                        try {
                            $plain = Crypt::decryptString($str);
                        } catch (\Throwable $e) {
                            $plain = $str;
                        }
                        $plain = PiiBlindIndex::normalizeCpf($plain);
                        $h = PiiBlindIndex::cpfHash($plain);
                        $update = ['PESSOA_CPF_HASH' => $h];
                        if ($doFle) {
                            $update[$cpfCol] = Crypt::encryptString($plain);
                        }
                        DB::table('PESSOA')->where('PESSOA_ID', $id)->update($update);
                        $n++;
                    }
                });
            }, 'PESSOA_ID');

        $this->info("Processados {$n} registos PESSOA (último PESSOA_ID lido: {$lastId}).");

        return 0;
    }
}
