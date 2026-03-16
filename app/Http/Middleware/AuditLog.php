<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SEC-04: Middleware de auditoria para mutações (POST/PUT/PATCH/DELETE).
 * Grava em AUDIT_LOG (tabela criada pela migration 2026_02_22_000002).
 * Registrar no grupo api/v3 em web.php: ->middleware(['web','auth','audit'])
 */
class AuditLog
{
    /** Tabelas a deduzir do path */
    private static array $pathMap = [
        'consignacao' => 'CONSIG_CONTRATO',
        'folhas' => 'FOLHA',
        'funcionarios' => 'FUNCIONARIO',
        'exoneracao' => 'FUNCIONARIO',
        'progressao' => 'HISTORICO_PROGRESSAO',
        'banco-horas' => 'BANCO_HORAS',
        'atestados' => 'ATESTADO_MEDICO',
        'diarias' => 'DIARIA_SOLICITACAO',
        'acumulacao' => 'ACUMULACAO_CARGO',
        'transparencia' => 'TRANSPARENCIA_EXPORTACAO',
        'pss' => 'PSS_EDITAL',
        'terceirizados' => 'TERCEIRIZADO_EMPRESA',
        'sagres' => 'SAGRES_ARQUIVO',
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Só audita mutações com usuário autenticado
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        if (!Auth::check()) {
            return $response;
        }

        try {
            $tabela = $this->inferirTabela($request->path());
            $dados = $request->except(['_token', 'USUARIO_SENHA', 'password', 'password_confirmation']);

            DB::table('AUDIT_LOG')->insert([
                'USUARIO_ID' => Auth::id(),
                'ACAO' => $request->method() . ' /' . $request->path(),
                'TABELA' => $tabela,
                'DADOS_NOVOS' => json_encode($dados, JSON_UNESCAPED_UNICODE),
                'IP' => $request->ip(),
                'USER_AGENT' => substr($request->userAgent() ?? '', 0, 200),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Nunca quebrar a requisição por falha na auditoria
            Log::channel('security')->error('audit_log_falhou', [
                'erro' => $e->getMessage(),
                'method' => $request->method(),
                'path' => $request->path(),
            ]);
        }

        return $response;
    }

    private function inferirTabela(string $path): string
    {
        // Remove prefixo api/v3/
        $segmento = explode('/', ltrim(str_replace('api/v3/', '', $path), '/'))[0];
        return self::$pathMap[$segmento] ?? strtoupper($segmento);
    }
}
