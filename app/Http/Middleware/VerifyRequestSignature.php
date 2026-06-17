<?php

namespace App\Http\Middleware;

use App\Support\RequestSigning;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
class VerifyRequestSignature
{
    public function handle(Request $request, Closure $next)
    {
        if (! RequestSigning::enabled()) {
            return $next($request);
        }

        if (! in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        if (! Auth::check()) {
            return $next($request);
        }

        $ct = (string) $request->header('Content-Type', '');
        if ($ct !== '' && stripos($ct, 'multipart/form-data') !== false) {
            return $next($request);
        }

        $request->attributes->set('gente.assinatura_validada', false);

        $sig = (string) $request->header('X-Gente-Signature', '');
        $ts = (string) $request->header('X-Gente-Timestamp', '');

        if ($sig === '' || $ts === '' || ! ctype_digit($ts)) {
            $this->logFraud($request, 'CABEÇALHOS_AUSENTES', $sig, $ts);

            return response()->json([
                'erro' => 'Requisição sem assinatura HMAC (Frente 2: integridade do payload).',
                'code' => 'GENTE_SIGNATURE_REQUIRED',
            ], 403);
        }

        $now = (int) (microtime(true) * 1000);
        $t = (int) $ts;
        if (abs($now - $t) > RequestSigning::leewayMs()) {
            $this->logFraud($request, 'REPLAY_OU_RELÓGIO', $sig, $ts);

            return response()->json([
                'erro' => 'Timestamp fora da janela permitida; possível replay ou relógio desalinhado.',
                'code' => 'GENTE_SIGNATURE_REPLAY',
            ], 403);
        }

        $secret = RequestSigning::ensureSessionSecret($request);
        $raw = $request->getContent();
        if ($raw === false) {
            $raw = '';
        }
        $path = $request->getPathInfo();
        if ($path === '') {
            $path = '/';
        }

        $expected = RequestSigning::expectedHexSignature(
            $request->getMethod(),
            $path,
            $ts,
            (string) $raw,
            $secret
        );

        if (! hash_equals(strtolower($expected), strtolower($sig))) {
            $this->logFraud($request, 'HMAC_DIFERENTE', $sig, $ts);

            return response()->json([
                'erro' => 'Assinatura de payload inválida (HMAC).',
                'code' => 'GENTE_SIGNATURE_INVALID',
            ], 403);
        }

        $request->attributes->set('gente.assinatura_validada', true);

        return $next($request);
    }

    private function logFraud(Request $request, string $reason, string $sig, string $ts): void
    {
        Log::channel('security')->warning('gente_assinatura_invalida', [
            'razao' => $reason,
            'path' => $request->getPathInfo(),
            'ip' => $request->ip(),
        ]);

        if (! Schema::hasTable('AUDIT_LOG')) {
            return;
        }

        $auditCols = Schema::getColumnListing('AUDIT_LOG');
        $byLower = [];
        foreach ($auditCols as $c) {
            $byLower[strtolower($c)] = $c;
        }
        $pick = function (string ...$candidates) use ($byLower): ?string {
            foreach ($candidates as $name) {
                $k = $byLower[strtolower($name)] ?? null;
                if ($k !== null) {
                    return $k;
                }
            }

            return null;
        };

        $row = [];
        if ($c = $pick('ACAO', 'acao')) {
            $row[$c] = 'GENTE_ASSINATURA_INVALIDA';
        }
        if ($c = $pick('DADOS_NOVOS', 'dados_novos', 'contexto')) {
            $row[$c] = json_encode([
                'razao' => $reason,
                'ts_header' => $ts,
                'ip' => $request->ip(),
            ], JSON_UNESCAPED_UNICODE);
        }
        if ($c = $pick('USUARIO_ID', 'usuario_id')) {
            $uid = Auth::id();
            if ($uid !== null) {
                $row[$c] = (int) $uid;
            }
        }
        if ($c = $pick('ASSINATURA_VALIDADA', 'assincatura_validada')) {
            $row[$c] = 0;
        }
        if ($c = $pick('IP', 'ip')) {
            $row[$c] = (string) $request->ip();
        }
        if (empty($row)) {
            return;
        }
        try {
            \App\Support\GenteAuditWriter::insertChainedRow($row);
        } catch (\Throwable $e) {
            Log::warning('Falha ao inserir AUDIT_LOG (assinatura): ' . $e->getMessage());
        }
    }
}
