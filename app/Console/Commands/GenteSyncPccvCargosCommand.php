<?php

namespace App\Console\Commands;

use App\Models\PccvDominio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Atribui CARGO.PCCV_ID com base em heurísticas sobre nome e carreira (legado).
 * Revisão humana no cadastro de cargos continua recomendada.
 */
class GenteSyncPccvCargosCommand extends Command
{
    protected $signature = 'gente:sync-pccv-cargos
                            {--dry-run : Apenas exibe o que seria alterado}
                            {--force : Reavalia também cargos que já têm PCCV_ID}
                            {--fill-geral : Atribui GERAL aos cargos sem regra específica ao final}';

    protected $description = 'Sincroniza PCCV_ID dos cargos com o domínio PCCV (MAGISTERIO, SAUDE, SEGURANCA, GERAL).';

    public function handle(): int
    {
        if (! Schema::hasTable('PCCV_DOMINIO') || ! Schema::hasTable('CARGO')) {
            $this->error('Tabelas PCCV_DOMINIO ou CARGO inexistentes. Rode as migrations.');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('CARGO', 'PCCV_ID')) {
            $this->error('Coluna CARGO.PCCV_ID inexistente. Rode a migration de PCCV.');

            return self::FAILURE;
        }

        $map = PccvDominio::query()
            ->where('ATIVO', true)
            ->whereIn('SIGLA', ['MAGISTERIO', 'GERAL', 'SAUDE', 'SEGURANCA'])
            ->pluck('PCCV_DOMINIO_ID', 'SIGLA')
            ->all();

        foreach (['MAGISTERIO', 'GERAL', 'SAUDE', 'SEGURANCA'] as $sigla) {
            if (empty($map[$sigla])) {
                $this->warn("Domínio com SIGLA={$sigla} não encontrado. Execute: php artisan db:seed --class=PccvDominioSeeder");

                return self::FAILURE;
            }
        }

        $q = DB::table('CARGO');
        if (! $this->option('force')) {
            $q->whereNull('PCCV_ID');
        }

        $cargos = $q->orderBy('CARGO_ID')->get();
        $dry = (bool) $this->option('dry-run');
        $fillGeral = (bool) $this->option('fill-geral');

        $contagem = [
            'MAGISTERIO' => 0,
            'SAUDE' => 0,
            'SEGURANCA' => 0,
            'GERAL' => 0,
            'ignorados' => 0,
        ];

        foreach ($cargos as $c) {
            $sigla = $this->resolverSigla($c, $fillGeral);
            if ($sigla === null) {
                $contagem['ignorados']++;

                continue;
            }

            if (! $dry) {
                DB::table('CARGO')
                    ->where('CARGO_ID', $c->CARGO_ID)
                    ->update(['PCCV_ID' => $map[$sigla]]);
            }
            $contagem[$sigla]++;
        }

        $this->info($dry ? '[DRY-RUN] Nenhuma escrita no banco.' : 'Cargos atualizados.');

        foreach (['MAGISTERIO', 'SAUDE', 'SEGURANCA', 'GERAL'] as $k) {
            if ($contagem[$k] > 0) {
                $this->line("  {$k}: {$contagem[$k]}");
            }
        }
        if ($contagem['ignorados'] > 0) {
            $this->line('  (sem regra / mantidos nulos): '.$contagem['ignorados']);
        }

        return self::SUCCESS;
    }

    /**
     * @param  object{ CARGO_ID: int, CARGO_NOME?: string|null, CARGO_CARREIRA?: string|null }  $c
     */
    private function resolverSigla(object $c, bool $fillGeral): ?string
    {
        $nome = mb_strtoupper((string) ($c->CARGO_NOME ?? ''), 'UTF-8');
        $carreira = mb_strtoupper((string) ($c->CARGO_CARREIRA ?? ''), 'UTF-8');
        $blob = $nome.' | '.$carreira;

        if ($this->matchSeguranca($blob)) {
            return 'SEGURANCA';
        }
        if ($this->matchMagisterio($blob)) {
            return 'MAGISTERIO';
        }
        if ($this->matchSaude($blob)) {
            return 'SAUDE';
        }
        if ($fillGeral) {
            return 'GERAL';
        }

        return null;
    }

    private function matchSeguranca(string $blob): bool
    {
        return (bool) preg_match(
            '/GUARDA\s+MUNICIPAL|\bGM\b|GUARDA\s+CIDAD|AGENTE\s+DE\s+SEGUR|VIGILAN(C|Ç)A\s+URBAN/i',
            $blob
        );
    }

    private function matchMagisterio(string $blob): bool
    {
        return (bool) preg_match(
            '/PROFESSOR|PROFESSORA|MAGIST(E|Ê)R|MAGISTER|REGENTE|PEDAGOG|COORDENADOR\s+PEDAG|EDUCA(C|Ç)(A|Ã)O\s+INFANTIL|DOCENTE/i',
            $blob
        );
    }

    private function matchSaude(string $blob): bool
    {
        return (bool) preg_match(
            '/M(E|É)DICO|ENFERM|TE(C|T)NICO(\s+EM)?\s+ENFERM|NUTRICION|FISIOTER|FONOAUDI|BIOM(E|É)D|FARMAC(EUT|ÊUT)|VIGIL(A|Â)NCIA\s+SANIT|AGENTE\s+COMUNIT(\.|ÁRIO)|\bACS\b|AUXILIAR\s+EM\s+SA(M|Ú)DE|CIRURGI(C|Ç)(A|Ã)O|ODONTO/i',
            $blob
        );
    }
}
