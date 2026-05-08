<?php

namespace App\Http\Middleware;

use App\Support\GenteAuditWriter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    /** Rotas de leitura sensível (GET) que também devem ser auditadas. */
    private static array $sensitiveReadRules = [
        ['needle' => '/api/v3/meus-holerites', 'label' => 'Visualizou Folha/Salário', 'priority' => 'MEDIA'],
        ['needle' => '/api/v3/folha', 'label' => 'Visualizou Folha/Salário', 'priority' => 'MEDIA'],
        ['needle' => '/api/v3/contratos', 'label' => 'Visualizou Ficha Funcional', 'priority' => 'MEDIA'],
        ['needle' => '/api/v3/funcionarios', 'label' => 'Visualizou Dados Funcionais', 'priority' => 'MEDIA'],
        ['needle' => '/api/v3/medicina', 'label' => 'Visualizou Exames Ocupacionais', 'priority' => 'ALTA'],
        ['needle' => '/api/v3/medicina-admin', 'label' => 'Visualizou Exames Ocupacionais (Admin)', 'priority' => 'ALTA'],
        ['needle' => '/api/v3/seguranca', 'label' => 'Visualizou Dados de Saúde/SST', 'priority' => 'ALTA'],
        ['needle' => '/api/v3/seguranca-admin', 'label' => 'Visualizou Dados de Saúde/SST (Admin)', 'priority' => 'ALTA'],
        ['needle' => '/api/v3/seguranca-trabalho', 'label' => 'Visualizou Dados de Saúde/SST', 'priority' => 'ALTA'],
        ['needle' => '/api/v3/atestados', 'label' => 'Visualizou Dados Médicos', 'priority' => 'ALTA'],
    ];

    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true) && ! Auth::check()) {
            return response()->json(['erro' => 'Não autenticado. Ação exige USUARIO_ID na trilha de auditoria.'], 401);
        }

        $response = $next($request);

        if (!Auth::check()) {
            return $response;
        }

        $isMutation = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $sensitiveContext = $this->sensitiveReadContext($request);
        $isSensitiveRead = $request->method() === 'GET' && $sensitiveContext !== null;

        if (! $isMutation && ! $isSensitiveRead) {
            return $response;
        }

        try {
            $tabela = $this->inferirTabela($request->path());
            $dados = $request->except(['_token', 'USUARIO_SENHA', 'password', 'password_confirmation']);
            $meta = [
                'event_type' => $isSensitiveRead ? 'LEITURA_SENSIVEL' : 'MUTACAO',
                'priority' => $isSensitiveRead ? ($sensitiveContext['priority'] ?? 'MEDIA') : 'NORMAL',
                'context' => $isSensitiveRead ? ($sensitiveContext['label'] ?? null) : null,
                'target_id' => $this->extractTargetId($request),
                'path' => '/' . ltrim($request->path(), '/'),
            ];

            GenteAuditWriter::insertChainedRow([
                'USUARIO_ID' => Auth::id(),
                'ACAO' => $isSensitiveRead ? 'ACESSO_SENSIVEL' : ($request->method() . ' /' . $request->path()),
                'TABELA' => $tabela,
                'DADOS_NOVOS' => json_encode(array_merge($dados, ['__audit' => $meta]), JSON_UNESCAPED_UNICODE),
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

    private function sensitiveReadContext(Request $request): ?array
    {
        $path = '/' . ltrim($request->path(), '/');
        foreach (self::$sensitiveReadRules as $rule) {
            if (str_contains($path, $rule['needle'])) {
                return $rule;
            }
        }

        return null;
    }

    private function extractTargetId(Request $request): ?int
    {
        foreach (['id', 'funcionario_id', 'usuario_id', 'detalheFolhaId', 'detalhe_folha_id'] as $k) {
            $v = $request->input($k);
            if (is_numeric($v) && (int) $v > 0) {
                return (int) $v;
            }
        }
        foreach (explode('/', trim($request->path(), '/')) as $segment) {
            if (ctype_digit($segment) && (int) $segment > 0) {
                return (int) $segment;
            }
        }

        return null;
    }
}
