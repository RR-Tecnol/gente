<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve SETOR_ID de quarentena para lotações sem mapeamento válido no organograma GENTE.
 */
final class SisfolhaQuarantineResolver
{
    public static function resolveSetorId(): int
    {
        $fixed = (int) config('gente_sisfolha_import.quarentena_setor_id', 0);
        if ($fixed > 0 && self::setorValido($fixed)) {
            return $fixed;
        }

        $siglaUn = (string) config('gente_sisfolha_import.quarentena_unidade_sigla', 'MIG-NAO-CLASS');
        $nomeSetor = (string) config('gente_sisfolha_import.quarentena_setor_nome', 'A CLASSIFICAR (import)');

        $uid = (int) (DB::table('UNIDADE')->where('UNIDADE_SIGLA', $siglaUn)->value('UNIDADE_ID') ?? 0);
        if ($uid <= 0 && Schema::hasTable('UNIDADE')) {
            $u = ['UNIDADE_NOME' => 'Migração — A CLASSIFICAR (import)', 'UNIDADE_SIGLA' => $siglaUn];
            if (Schema::hasColumn('UNIDADE', 'UNIDADE_ATIVA')) {
                $u['UNIDADE_ATIVA'] = 1;
            }
            if (Schema::hasColumn('UNIDADE', 'UNIDADE_ATIVO')) {
                $u['UNIDADE_ATIVO'] = 1;
            }
            if (Schema::hasColumn('UNIDADE', 'UNIDADE_TIPO')) {
                $u['UNIDADE_TIPO'] = 0;
            }
            $uid = (int) DB::table('UNIDADE')->insertGetId($u);
        }

        $sid = (int) (DB::table('SETOR')
            ->where('UNIDADE_ID', $uid)
            ->where('SETOR_NOME', $nomeSetor)
            ->value('SETOR_ID') ?? 0);
        if ($sid > 0) {
            return $sid;
        }

        $s = ['UNIDADE_ID' => $uid, 'SETOR_NOME' => $nomeSetor];
        if (Schema::hasColumn('SETOR', 'SETOR_ATIVO')) {
            $s['SETOR_ATIVO'] = 1;
        }

        return (int) DB::table('SETOR')->insertGetId($s);
    }

    private static function setorValido(int $setorId): bool
    {
        if (! Schema::hasTable('SETOR')) {
            return false;
        }
        $row = DB::table('SETOR')->where('SETOR_ID', $setorId)->first(['UNIDADE_ID']);

        return $row && (int) ($row->UNIDADE_ID ?? 0) > 0;
    }
}
