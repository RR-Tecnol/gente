<?php

namespace App\Listeners;

use App\Events\HoneytokenTriggered;
use App\Security\IpBlocklistService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class HandleHoneytokenAlarm
{
    public function handle(HoneytokenTriggered $e): void
    {
        Log::emergency('GENTE_HONEYTOKEN: intrusão ou sonda detetada', [
            'kind' => $e->kind,
            'funcionario_id' => $e->funcionarioId,
            'ip' => $e->ip,
            'path' => $e->path,
            'usuario_id' => $e->userId,
        ]);

        if (Schema::hasTable('AUDIT_LOG')) {
            $row = $this->auditRow($e);
            if (! empty($row)) {
                try {
                    \App\Support\GenteAuditWriter::insertChainedRow($row);
                } catch (\Throwable $t) {
                    Log::error('GENTE_HONEYTOKEN: falha insert AUDIT_LOG: ' . $t->getMessage());
                }
            }
        }

        if ($e->kind === 'canary_route' && (bool) config('gente.honeytokens.blocklist_canary_24h', true)) {
            IpBlocklistService::block($e->ip, 'TRIPWIRE_CANARY', 24);
        } elseif ((bool) config('gente.honeytokens.blocklist_on_honey_touch', false) && $e->kind === 'honey_funcionario') {
            IpBlocklistService::block($e->ip, 'HONEYTOKEN_ACESSO', 24);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function auditRow(HoneytokenTriggered $e): array
    {
        if (! Schema::hasTable('AUDIT_LOG')) {
            return [];
        }
        $cols = Schema::getColumnListing('AUDIT_LOG');
        $by = [];
        foreach ($cols as $c) {
            $by[strtolower($c)] = $c;
        }
        $pick = function (string ...$names) use ($by): ?string {
            foreach ($names as $n) {
                if (isset($by[strtolower($n)])) {
                    return $by[strtolower($n)];
                }
            }

            return null;
        };
        $row = [];
        if ($a = $pick('ACAO', 'acao')) {
            $row[$a] = 'SISTEMA_INTRUSAO_DETECTADA';
        }
        if ($a = $pick('TABELA', 'tabela')) {
            $row[$a] = $e->kind === 'canary_route' ? 'TRIPWIRE' : 'HONEYTOKEN';
        }
        if ($a = $pick('DADOS_NOVOS', 'dados_novos', 'contexto')) {
            $row[$a] = json_encode([
                'kind' => $e->kind,
                'funcionario_id' => $e->funcionarioId,
                'ip' => $e->ip,
                'path' => $e->path,
            ], JSON_UNESCAPED_UNICODE);
        } elseif ($a = $pick('DADOS_ANTIGOS', 'dados_antigos')) {
            $row[$a] = json_encode(['path' => $e->path], JSON_UNESCAPED_UNICODE);
        }
        if ($a = $pick('USUARIO_ID', 'usuario_id')) {
            $row[$a] = $e->userId > 0 ? $e->userId : null;
        }
        if ($a = $pick('IP', 'ip')) {
            $row[$a] = $e->ip;
        }
        if ($a = $pick('USER_AGENT', 'user_agent')) {
            $row[$a] = $e->userAgent;
        }
        if ($a = $pick('CREATED_AT', 'created_at', 'DATA_HORA')) {
            $row[$a] = now();
        }
        if ($a = $pick('updated_at', 'UPDATED_AT')) {
            $row[$a] = now();
        }
        if ($a = $pick('evento', 'EVENTO', 'event_type')) {
            $row[$a] = 'GENTE_HONEYTOKEN';
        }

        return $row;
    }
}
