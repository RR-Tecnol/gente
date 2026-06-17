<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;

class GenteAuditarRotasMutacaoCommand extends Command
{
    protected $signature = 'gente:auditar-rotas-mutacao
        {--json : Saída JSON}
        {--incluir-guest : Listar também rotas que usam o middleware guest (ex.: login)}';

    protected $description = 'SEC: lista rotas POST/PUT/PATCH/DELETE sem autenticação (potencial mutação pública)';

    public function handle(): int
    {
        $mutar = ['POST', 'PUT', 'PATCH', 'DELETE'];
        $suspeitas = [];
        $incluirGuest = (bool) $this->option('incluir-guest');

        /** @var \Illuminate\Routing\Route $route */
        foreach (Route::getRoutes() as $route) {
            $methods = array_filter($route->methods(), static fn (string $m) => $m !== 'HEAD');
            if (!array_intersect($methods, $mutar)) {
                continue;
            }
            $mw = $route->gatherMiddleware();
            if (!$incluirGuest && in_array('guest', $mw, true)) {
                continue;
            }
            if ($this->possuiCamadaDeAutenticacao($mw)) {
                continue;
            }
            $uri = (string) $route->uri();
            if ($this->excecaoRotaEstritamentePublica($uri, $route)) {
                continue;
            }
            $suspeitas[] = [
                'metodos' => array_values(array_intersect($methods, $mutar)),
                'uri' => $uri,
                'name' => $route->getName(),
                'middleware' => $mw,
                'so_apenas_web' => in_array('web', $mw, true) && !in_array('api', $mw, true),
                'action' => $route->getActionName(),
            ];
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => $suspeitas === [],
                'total' => count($suspeitas),
                'nota' => 'Sem middleware auth/guest: pode ser JWT no controller, token em path, ou gap real. Revisar doc docs/SEC_AUDITORIA_ROTAS_MUTACAO_2026-04-28.md',
                'rotas' => $suspeitas,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            if ($suspeitas === []) {
                $this->info('Nenhuma rota de mutação (após excluir fluxos login/dev/debug) sem middleware de autenticação listada.');
            } else {
                $this->warn('Rotas POST/PUT/PATCH/DELETE sem middleware auth (após exclusões) — ' . count($suspeitas) . ' ocorrência(s).');
                $this->line('Ver `docs/SEC_AUDITORIA_ROTAS_MUTACAO_2026-04-28.md` — muitas rotas `api/v3` usam grupo `web` e podem exigir sessão; outras exigem revisão de código.');
                foreach ($suspeitas as $r) {
                    $w = $r['so_apenas_web'] ? ' [web only]' : '';
                    $this->line('- [' . implode('|', $r['metodos']) . '] ' . $r['uri'] . $w . ' :: ' . $r['action']);
                }
            }
        }

        return $suspeitas === [] ? self::SUCCESS : self::FAILURE;
    }

    private function possuiCamadaDeAutenticacao(array $middleware): bool
    {
        foreach ($middleware as $m) {
            if (!is_string($m)) {
                continue;
            }
            if ($m === 'auth' || $m === 'usuario.externo' || $m === 'perfil' || $m === 'auth.basic' || $m === 'auth:sanctum' || $m === 'auth:api' || $m === 'can' || $m === 'password.confirm' || $m === 'signed') {
                return true;
            }
            if (str_starts_with($m, 'auth:')) {
                return true;
            }
        }

        return false;
    }

    private function excecaoRotaEstritamentePublica(string $uri, $route): bool
    {
        if (str_contains($uri, '_ignition') || str_contains($uri, 'telescope') || str_contains($uri, 'horizon') || $uri === 'sanctum/csrf-cookie') {
            return true;
        }
        if (str_contains($uri, 'log-viewer') || str_contains($uri, '_debugbar')) {
            return true;
        }
        if (Str::startsWith($uri, 'dev/') || Str::startsWith($uri, 'api/auth/') || Str::startsWith($uri, 'password/')) {
            return true;
        }
        if (in_array($uri, ['logout', 'registrar'], true)) {
            return true;
        }
        if (Str::startsWith($uri, 'quiosque/')) {
            return true;
        }
        if (Str::startsWith($uri, 'api/v3/autocadastro')) {
            return true;
        }
        if (Str::startsWith($uri, 'api/v3/beneficios/solicitar')) {
            return true;
        }
        if (Str::startsWith($uri, 'api/v3/pesquisas')) {
            return true;
        }
        if (Str::contains($uri, 'transparencia/exportar') && in_array('tenant.resolve', $route->gatherMiddleware(), true)) {
            return true;
        }
        $name = (string) $route->getName();
        if (Str::startsWith($name, 'certificado_ssl')) {
            return true;
        }
        // Terminal de ponto: autenticação por Bearer do terminal (não é sessão Laravel)
        if ($uri === 'api/ponto/bater') {
            return true;
        }
        // App móvel de ponto: login público; registo de batida autenticado por JWT (HMAC), não por middleware
        if (Str::startsWith($uri, 'api/v3/ponto/app/login')) {
            return true;
        }
        if (Str::startsWith($uri, 'api/v3/ponto/app/registrar')) {
            return true;
        }

        return false;
    }
}
