<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Smoke GET das rotas /api/v3 consideradas críticas para a Teia.
 *
 * Sem --http: só verifica se o Laravel consegue fazer match da rota (não 404 de roteamento).
 * Com --http: dispatch real pelo Kernel (sem sessão costuma dar 401/302 — conta como “fio ok”).
 */
class GentePulseVitalidadeCommand extends Command
{
    protected $signature = 'gente:pulse-vitalidade {--http : Dispatch GET real pelo HTTP Kernel (resposta típica 401 sem login).}';

    protected $description = 'Verificação rápida (pulso) de 15 GETs críticos em /api/v3';

    /** [rótulo, método, path sem barra inicial, query string opcional] */
    private const PULSO = [
        ['Folha (lista)', 'GET', 'api/v3/folhas', ''],
        ['Atestados (lista)', 'GET', 'api/v3/atestados', ''],
        ['Ponto (resumo/mês)', 'GET', 'api/v3/ponto', ''],
        ['Secretarias (aux.)', 'GET', 'api/v3/secretarias', ''],
        ['Funcionários (index)', 'GET', 'api/v3/funcionarios', ''],
        ['Transparência (histórico)', 'GET', 'api/v3/transparencia/historico', ''],
        ['Comunicados', 'GET', 'api/v3/comunicados', ''],
        ['Notificações', 'GET', 'api/v3/notificacoes', ''],
        ['Organograma', 'GET', 'api/v3/organograma', ''],
        ['Abonos (gestor)', 'GET', 'api/v3/abonos-gestao', 'mes=1&ano=2024'],
        ['Banco de horas', 'GET', 'api/v3/banco-horas', ''],
        ['Férias (lista GET)', 'GET', 'api/v3/ferias', ''],
        ['Holerites (autenticado)', 'GET', 'api/v3/meus-holerites', ''],
        ['Contra-cheque PDF (path)', 'GET', 'api/v3/contra-cheque/1/202401/pdf', ''],
        ['E-social (pendências)', 'GET', 'api/v3/esocial/pendencias', ''],
    ];

    public function handle(): int
    {
        $rows = [];
        $brokenRoute = 0;
        $httpBroken = 0;
        $httpOk = 0;

        foreach (self::PULSO as [$label, $method, $path, $query]) {
            $q = $query === '' ? [] : [];
            if ($query !== '') {
                parse_str($query, $q);
            }
            $url = '/' . ltrim($path, '/') . ($query !== '' ? '?' . $query : '');

            $regOk = 'sim';
            try {
                $req = Request::create($url, $method, $q);
                Route::getRoutes()->match($req);
            } catch (\Throwable) {
                $regOk = 'NÃO';
                $brokenRoute++;
            }

            $http = '— (sem --http)';
            if ($this->option('http')) {
                $req = Request::create($url, $method, $q);
                try {
                    $kernel = app()->make(\Illuminate\Contracts\Http\Kernel::class);
                    $res = $kernel->handle($req);
                    $st = $res->getStatusCode();
                    $http = (string) $st;
                    if (in_array($st, [200, 302, 401, 403, 422], true)) {
                        $httpOk++;
                    } elseif (in_array($st, [404, 405, 500], true)) {
                        $httpBroken++;
                    } else {
                        $httpOk++;
                    }
                } catch (\Throwable $e) {
                    $http = 'ex: ' . $e->getMessage();
                    $httpBroken++;
                }
            }

            $rows[] = [
                'Área' => $label,
                'Mét.' => $method,
                'Path' => $path,
                'Registrada?' => $regOk,
                'HTTP' => $http,
            ];
        }

        $this->table(
            ['Área', 'Mét.', 'Path', 'Registrada?', 'HTTP'],
            array_map('array_values', $rows)
        );
        if ($this->option('http')) {
            $this->line("HTTP: respostas 200/302/401/403/422 consideradas ‘conversa’ (rota conhecida; auth pode faltar).");
            $this->line("Aprox.: ok de pulso={$httpOk}, suspeito 404/405/500={$httpBroken}");
        }
        if ($brokenRoute > 0) {
            $this->warn("{$brokenRoute} path(s) não casam com nenhuma rota Laravel (fio partido a nível de definição).");
            return 1;
        }
        $this->info('Todas as 15 entradas acima têm rota GET registrada em route:list (match).');

        return 0;
    }
}
