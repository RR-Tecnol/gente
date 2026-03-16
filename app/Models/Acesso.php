<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property integer ACESSO_ID
 * @property integer APLICACAO_ID
 * @property integer PERFIL_ID
 * @property integer ACESSO_ATIVO
 */
class Acesso extends Model
{
    use HasFactory;

    protected $table = "ACESSO";
    protected $primaryKey = "ACESSO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "APLICACAO_ID",
        "PERFIL_ID",
        "ACESSO_ATIVO",
    ];
    protected $casts = [
        "ACESSO_ID" => "integer",
        "APLICACAO_ID" => "integer",
        "PERFIL_ID" => "integer",
        "ACESSO_ATIVO" => "integer",
    ];

    public function aplicacao()
    {
        return $this->hasOne(Aplicacao::class, "APLICACAO_ID", "APLICACAO_ID");
    }

    public function perfil()
    {
        return $this->hasOne(Perfil::class, "PERFIL_ID", "PERFIL_ID");
    }

    public static function deleteByPerfilId($perfilId)
    {
        self::with([])->where("PERFIL_ID", $perfilId)->delete();
    }

    public static function getByUsuarioId($usuarioId)
    {
        $aplicacaoIds = DB::select(
            "SELECT ACS.APLICACAO_ID
                    FROM ACESSO ACS
                    INNER JOIN APLICACAO A on A.APLICACAO_ID = ACS.APLICACAO_ID
                    WHERE ACS.PERFIL_ID IN (SELECT UP.PERFIL_ID FROM USUARIO_PERFIL UP WHERE UP.USUARIO_ID = $usuarioId AND UP.USUARIO_PERFIL_ATIVO = 1)
                    AND A.APLICACAO_PAI_ID IS NULL
                    GROUP BY ACS.APLICACAO_ID"
        );
        $aplicacaoIdsArray = [];
        if (count($aplicacaoIds) > 0) {
            foreach ($aplicacaoIds as $aplicacaoId) {
                $aplicacaoIdsArray[] = $aplicacaoId->APLICACAO_ID;
            }
        }
        $aplicacoesPai = Aplicacao::with([])->whereIn("APLICACAO_ID", $aplicacaoIdsArray)->where("APLICACAO_ATIVA", 1)->get();
        if ($aplicacoesPai) {
            $aplicacoesPaiArray = $aplicacoesPai->toArray();
            for ($i = 0; $i < count($aplicacoesPaiArray); $i++) {
                $children = DB::select(
                    "SELECT ACS.APLICACAO_ID,
                                    A.APLICACAO_PAI_ID
                            FROM ACESSO ACS
                            INNER JOIN APLICACAO A on A.APLICACAO_ID = ACS.APLICACAO_ID
                            WHERE ACS.PERFIL_ID IN (SELECT UP.PERFIL_ID FROM USUARIO_PERFIL UP WHERE UP.USUARIO_ID = $usuarioId AND UP.USUARIO_PERFIL_ATIVO = 1)
                            AND A.APLICACAO_PAI_ID = {$aplicacoesPaiArray[$i]['APLICACAO_ID']}
                            GROUP BY ACS.APLICACAO_ID, A.APLICACAO_PAI_ID"
                );
                $childrenArray = [];
                if ($children) {
                    foreach ($children as $child) {
                        $childrenArray[] = $child->APLICACAO_ID;
                    }
                }
                $aplicacoesPaiArray[$i]['children'] = Aplicacao::with([])
                    ->whereIn("APLICACAO_ID", $childrenArray)
                    ->where("APLICACAO_ATIVA", 1)
                    ->orderBy("APLICACAO_ORDEM")
                    ->get()
                    ->toArray();
                $aplicacoesPaiArray[$i]['model'] = false;
            }
            $aplicacoesFiltradas = [];
            foreach ($aplicacoesPaiArray as $pai) {
                // Se é um item pai de agrupamento e não sobrou nenhum filho ativo, ocultamos
                if (count($pai['children']) === 0 && (empty($pai['APLICACAO_URL']) || $pai['APLICACAO_URL'] == '#')) {
                    continue;
                }
                $aplicacoesFiltradas[] = $pai;
            }

            return $aplicacoesFiltradas;
        }
    }
}
