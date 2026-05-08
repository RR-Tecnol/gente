<?php

namespace App\Support\Scripts;

use App\Models\Usuario;
use App\Support\LoginLookupNormalizer;
use App\Support\UsuarioLoginResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fusão de registros duplicados em USUARIO (mesmo FUNCIONARIO_ID ou mesmo login normalizado),
 * centralizando USUARIO_PERFIL (e vínculos diretos) no registro mais completo.
 *
 *   php artisan gente:unificar-usuarios
 *   php artisan gente:unificar-usuarios --dry-run
 */
final class UnificarUsuarios
{
    /** @return array{grupos: int, mesclados: int, removidos: int, detalhe: list<array<string, mixed>>} */
    public static function run(bool $dryRun = false): array
    {
        if (! Schema::hasTable('USUARIO')) {
            return ['grupos' => 0, 'mesclados' => 0, 'removidos' => 0, 'detalhe' => [['erro' => 'Tabela USUARIO inexistente']]];
        }

        $cols = ['USUARIO_ID', 'USUARIO_LOGIN', 'USUARIO_NOME', 'USUARIO_EMAIL', 'USUARIO_ATIVO'];
        if (Schema::hasColumn('USUARIO', 'FUNCIONARIO_ID')) {
            $cols[] = 'FUNCIONARIO_ID';
        }
        $rows = DB::table('USUARIO')->select($cols)->orderBy('USUARIO_ID')->get();

        $allIds = $rows->pluck('USUARIO_ID')->map(fn ($id) => (int) $id)->all();
        $uf = new class($allIds) {
            public function __construct(private array $ids)
            {
                $this->parent = [];
                foreach ($this->ids as $id) {
                    $this->parent[$id] = $id;
                }
            }

            private array $parent;

            public function find(int $x): int
            {
                if ($this->parent[$x] !== $x) {
                    $this->parent[$x] = $this->find($this->parent[$x]);
                }

                return $this->parent[$x];
            }

            public function union(int $a, int $b): void
            {
                $ra = $this->find($a);
                $rb = $this->find($b);
                if ($ra !== $rb) {
                    $this->parent[$ra] = $rb;
                }
            }
        };

        $byFunc = [];
        foreach ($rows as $r) {
            if (! property_exists($r, 'FUNCIONARIO_ID') || $r->FUNCIONARIO_ID === null || (int) $r->FUNCIONARIO_ID <= 0) {
                continue;
            }
            $fid = (int) $r->FUNCIONARIO_ID;
            if (! isset($byFunc[$fid])) {
                $byFunc[$fid] = [];
            }
            $byFunc[$fid][] = (int) $r->USUARIO_ID;
        }
        foreach ($byFunc as $ids) {
            $ids = array_values(array_unique($ids));
            if (count($ids) < 2) {
                continue;
            }
            $first = $ids[0];
            for ($i = 1, $c = count($ids); $i < $c; $i++) {
                $uf->union($first, $ids[$i]);
            }
        }

        $byLogin = [];
        foreach ($rows as $r) {
            $key = LoginLookupNormalizer::forDatabaseLookup((string) $r->USUARIO_LOGIN);
            if ($key === '') {
                continue;
            }
            if (! isset($byLogin[$key])) {
                $byLogin[$key] = [];
            }
            $byLogin[$key][] = (int) $r->USUARIO_ID;
        }
        foreach ($byLogin as $ids) {
            $ids = array_values(array_unique($ids));
            if (count($ids) < 2) {
                continue;
            }
            $first = $ids[0];
            for ($i = 1, $c = count($ids); $i < $c; $i++) {
                $uf->union($first, $ids[$i]);
            }
        }

        $components = [];
        foreach ($allIds as $id) {
            $root = $uf->find($id);
            if (! isset($components[$root])) {
                $components[$root] = [];
            }
            $components[$root][] = $id;
        }

        $grupos = 0;
        $removidos = 0;
        $detalhe = [];

        foreach ($components as $ids) {
            $ids = array_values(array_unique($ids));
            if (count($ids) < 2) {
                continue;
            }
            $grupos++;

            $models = Usuario::query()->whereIn('USUARIO_ID', $ids)->get();
            $keeper = UsuarioLoginResolver::pickBestUser($models);
            $dupes = $models->where('USUARIO_ID', '!=', $keeper->USUARIO_ID)->values();
            if ($dupes->isEmpty()) {
                continue;
            }

            $anyPrimeiro = $models->contains(function ($u) {
                return (int) ($u->USUARIO_PRIMEIRO_ACESSO ?? 0) === 1;
            });
            $anyAlterar = $models->contains(function ($u) {
                return (int) ($u->USUARIO_ALTERAR_SENHA ?? 0) === 1;
            });
            if (! $dryRun) {
                $k = Usuario::query()->find($keeper->USUARIO_ID);
                if ($k) {
                    if (Schema::hasColumn('USUARIO', 'USUARIO_PRIMEIRO_ACESSO')) {
                        $k->USUARIO_PRIMEIRO_ACESSO = $anyPrimeiro ? 1 : 0;
                    }
                    if (Schema::hasColumn('USUARIO', 'USUARIO_ALTERAR_SENHA')) {
                        $k->USUARIO_ALTERAR_SENHA = $anyAlterar ? 1 : 0;
                    }
                    $k->save();
                }
            }

            foreach ($dupes as $dup) {
                self::moverFilhos($keeper->USUARIO_ID, (int) $dup->USUARIO_ID, $dryRun);
            }

            foreach ($dupes as $dup) {
                $idDup = (int) $dup->USUARIO_ID;
                if (Schema::hasTable('FUNCIONARIO') && Schema::hasColumn('FUNCIONARIO', 'USUARIO_ID')) {
                    if (! $dryRun) {
                        DB::table('FUNCIONARIO')->where('USUARIO_ID', $idDup)->update(['USUARIO_ID' => $keeper->USUARIO_ID]);
                    }
                }
                if (! $dryRun) {
                    $dup->delete();
                }
                $removidos++;
            }

            $detalhe[] = [
                'manter' => $keeper->USUARIO_ID,
                'removidos' => $dupes->pluck('USUARIO_ID')->all(),
                'login' => $keeper->USUARIO_LOGIN,
            ];
        }

        return [
            'grupos' => $grupos,
            'mesclados' => $removidos,
            'removidos' => $removidos,
            'detalhe' => $detalhe,
        ];
    }

    /**
     * Fusão pontual (p.ex. hard_cleanup): move vínculos do duplicado para o keeper e remove o registro fantasma.
     */
    public static function mergeOneIntoKeeper(int $keeperId, int $dupId, bool $dryRun = false): void
    {
        self::moverFilhos($keeperId, $dupId, $dryRun);
        if ($dryRun) {
            return;
        }
        $dup = Usuario::query()->find($dupId);
        if (! $dup) {
            return;
        }
        if (Schema::hasTable('FUNCIONARIO') && Schema::hasColumn('FUNCIONARIO', 'USUARIO_ID')) {
            DB::table('FUNCIONARIO')->where('USUARIO_ID', $dupId)->update(['USUARIO_ID' => $keeperId]);
        }
        $dup->delete();
    }

    private static function moverFilhos(int $keeperId, int $dupId, bool $dryRun): void
    {
        if (Schema::hasTable('USUARIO_PERFIL')) {
            $perfs = DB::table('USUARIO_PERFIL')->where('USUARIO_ID', $dupId)->get();
            foreach ($perfs as $row) {
                $pid = (int) $row->PERFIL_ID;
                $exists = DB::table('USUARIO_PERFIL')
                    ->where('USUARIO_ID', $keeperId)
                    ->where('PERFIL_ID', $pid)
                    ->exists();
                if (! $dryRun) {
                    if ($exists) {
                        DB::table('USUARIO_PERFIL')
                            ->where('USUARIO_PERFIL_ID', $row->USUARIO_PERFIL_ID)
                            ->delete();
                    } else {
                        DB::table('USUARIO_PERFIL')
                            ->where('USUARIO_PERFIL_ID', $row->USUARIO_PERFIL_ID)
                            ->update(['USUARIO_ID' => $keeperId]);
                    }
                }
            }
        }

        if (Schema::hasTable('USUARIO_UNIDADE')) {
            $uus = DB::table('USUARIO_UNIDADE')->where('USUARIO_ID', $dupId)->get();
            foreach ($uus as $uu) {
                $unid = (int) $uu->UNIDADE_ID;
                $outro = DB::table('USUARIO_UNIDADE')
                    ->where('USUARIO_ID', $keeperId)
                    ->where('UNIDADE_ID', $unid)
                    ->first();
                if (! $dryRun) {
                    if ($outro) {
                        DB::table('USUARIO_UNIDADE')
                            ->where('USUARIO_UNIDADE_ID', $uu->USUARIO_UNIDADE_ID)
                            ->delete();
                    } else {
                        DB::table('USUARIO_UNIDADE')
                            ->where('USUARIO_UNIDADE_ID', $uu->USUARIO_UNIDADE_ID)
                            ->update(['USUARIO_ID' => $keeperId]);
                    }
                }
            }
        }

        if (Schema::hasTable('USUARIO_SETOR')) {
            $list = DB::table('USUARIO_SETOR')->where('USUARIO_ID', $dupId)->get();
            foreach ($list as $us) {
                $setorId = (int) $us->SETOR_ID;
                $outro = DB::table('USUARIO_SETOR')
                    ->where('USUARIO_ID', $keeperId)
                    ->where('SETOR_ID', $setorId)
                    ->first();
                if (! $dryRun) {
                    if ($outro) {
                        DB::table('USUARIO_SETOR')
                            ->where('USUARIO_SETOR_ID', $us->USUARIO_SETOR_ID)
                            ->delete();
                    } else {
                        DB::table('USUARIO_SETOR')
                            ->where('USUARIO_SETOR_ID', $us->USUARIO_SETOR_ID)
                            ->update(['USUARIO_ID' => $keeperId]);
                    }
                }
            }
        }
    }
}
