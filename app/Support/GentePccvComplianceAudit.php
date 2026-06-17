<?php

namespace App\Support;

use App\Services\Pccv\PccvJornadaViolation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Frente 5: auditoria de exceção PCCV cifrada (Laravel Crypt / APP_KEY).
 */
final class GentePccvComplianceAudit
{
    /**
     * @return bool true se inserido
     */
    public static function excecaoEscala(
        Request $request,
        PccvJornadaViolation $violation,
        $escalaId,
        $funcionarioId,
        $dataEscalaYmd,
        $justificativa
    ) {
        if (! Schema::hasTable('AUDIT_LOG')) {
            return false;
        }
        $cols = Schema::getColumnListing('AUDIT_LOG');
        $by = [];
        foreach ($cols as $c) {
            $by[strtolower($c)] = $c;
        }
        $pick = function (string ...$names) use ($by) {
            foreach ($names as $n) {
                if (isset($by[strtolower($n)])) {
                    return $by[strtolower($n)];
                }
            }

            return null;
        };
        $payload = [
            'infracao' => $violation->toArray(),
            'escala_id' => (int) $escalaId,
            'funcionario_id' => (int) $funcionarioId,
            'data_celula' => (string) $dataEscalaYmd,
            'justificativa_legal_cifrada' => Crypt::encryptString((string) $justificativa),
        ];
        $row = [];
        if ($c = $pick('ACAO', 'acao')) {
            $row[$c] = 'ESCALA_EXCECAO_PCCV';
        }
        if ($c = $pick('TABELA', 'tabela')) {
            $row[$c] = 'DETALHE_ESCALA_ITEM';
        }
        if ($c = $pick('DADOS_NOVOS', 'dados_novos', 'contexto')) {
            $row[$c] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif ($c = $pick('DADOS_ANTIGOS', 'dados_antigos')) {
            $row[$c] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($c = $pick('USUARIO_ID', 'usuario_id')) {
            $row[$c] = (int) GenteAuditWriter::requireAuthenticatedUserId();
        }
        if ($c = $pick('IP')) {
            $row[$c] = (string) $request->ip();
        }
        if ($c = $pick('USER_AGENT', 'user_agent')) {
            $row[$c] = \Illuminate\Support\Str::limit((string) $request->userAgent(), 255, '');
        }
        if ($c = $pick('created_at', 'CREATED_AT', 'DATA_HORA')) {
            $row[$c] = now();
        }
        if ($c = $pick('updated_at', 'UPDATED_AT')) {
            $row[$c] = now();
        }
        if (empty($row)) {
            return false;
        }
        GenteAuditWriter::insertChainedRow($row);

        return true;
    }
}
