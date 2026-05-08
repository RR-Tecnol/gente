<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expansão de escopo por organograma (setor pai → filhos), limitada ao pool de unidades do usuário.
 */
final class SetorHierarquiaEscopo
{
    /**
     * Setores âncora explícitos (USUARIO_SETOR) ou lotação ativa do vínculo FUNCIONARIO.
     * null = sem âncora: usar todo o pool (todos os setores das unidades USUARIO_UNIDADE).
     *
     * @return list<int>|null
     */
    public static function anchorsParaUsuario(Usuario $user): ?array
    {
        $fromVinculo = self::anchorsUsuarioSetor($user);
        if ($fromVinculo !== null) {
            return $fromVinculo;
        }

        return self::anchorsLotacaoFuncionario($user);
    }

    /**
     * @return list<int>|null null = tabela inexistente ou sem linhas (delegar à lotação)
     */
    private static function anchorsUsuarioSetor(Usuario $user): ?array
    {
        if (! Schema::hasTable('USUARIO_SETOR')) {
            return null;
        }
        $q = DB::table('USUARIO_SETOR')->where('USUARIO_ID', $user->USUARIO_ID);
        if (Schema::hasColumn('USUARIO_SETOR', 'USUARIO_SETOR_ATIVO')) {
            $q->where('USUARIO_SETOR_ATIVO', 1);
        } elseif (Schema::hasColumn('USUARIO_SETOR', 'ATIVO')) {
            $q->where('ATIVO', 1);
        }
        $ids = $q->pluck('SETOR_ID')->map(fn ($v) => (int) $v)->filter(fn ($id) => $id > 0)->unique()->values()->all();

        return $ids !== [] ? $ids : null;
    }

    /**
     * @return list<int>|null
     */
    private static function anchorsLotacaoFuncionario(Usuario $user): ?array
    {
        $fid = (int) ($user->FUNCIONARIO_ID ?? 0);
        if ($fid <= 0 || ! Schema::hasTable('LOTACAO')) {
            return null;
        }
        $q = DB::table('LOTACAO')->where('FUNCIONARIO_ID', $fid)->orderByDesc('LOTACAO_ID');
        if (Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM')) {
            $q->whereNull('LOTACAO_DATA_FIM');
        }
        $sid = (int) ($q->value('SETOR_ID') ?? 0);

        return $sid > 0 ? [$sid] : null;
    }

    /**
     * Inclui todos os descendentes cujo SETOR_PAI_ID forma árvore dentro do pool.
     *
     * @param  list<int>  $anchorIds  subset do pool
     * @param  list<int>  $poolIds
     * @return list<int>
     */
    public static function expandDescendentesNoPool(array $anchorIds, array $poolIds): array
    {
        $pool = array_flip($poolIds);
        $anchorIds = array_values(array_unique(array_filter($anchorIds, fn ($id) => $id > 0 && isset($pool[$id]))));
        if ($anchorIds === []) {
            return [];
        }
        if (! Schema::hasColumn('SETOR', 'SETOR_PAI_ID')) {
            return $anchorIds;
        }

        $childrenByParent = [];
        $rows = DB::table('SETOR')->whereIn('SETOR_ID', array_keys($pool))->get(['SETOR_ID', 'SETOR_PAI_ID']);
        foreach ($rows as $r) {
            $id = (int) $r->SETOR_ID;
            $pai = isset($r->SETOR_PAI_ID) ? (int) $r->SETOR_PAI_ID : 0;
            if ($pai > 0 && isset($pool[$pai])) {
                if (! isset($childrenByParent[$pai])) {
                    $childrenByParent[$pai] = [];
                }
                $childrenByParent[$pai][] = $id;
            }
        }

        $out = [];
        $queue = $anchorIds;
        $seen = [];
        while ($queue !== []) {
            $id = array_pop($queue);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $id;
            foreach ($childrenByParent[$id] ?? [] as $child) {
                if (isset($pool[$child]) && ! isset($seen[$child])) {
                    $queue[] = $child;
                }
            }
        }

        return array_values(array_unique($out));
    }
}
