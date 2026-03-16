<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int FIM_LOTACAO_ID
 * @property string FIM_LOTACAO_DESCRICAO
 * @property int FIM_LOTACAO_ATIVA
 *
 */
class FimLotacao extends Model
{
    protected $table = "FIM_LOTACAO";
    protected $primaryKey = "FIM_LOTACAO_ID";
    public $timestamps = false;
    protected $fillable = [
        "FIM_LOTACAO_DESCRICAO",
        "FIM_LOTACAO_ATIVA",
    ];
    protected $casts = [
        "FIM_LOTACAO_ATIVA" => "integer",
        "FIM_LOTACAO_ID" => "integer",
    ];

    public static function listar()
    {
        return self::where('FIM_LOTACAO_ATIVA', '=', 1)
            ->orderBy('FIM_LOTACAO_DESCRICAO')
            ->paginate();
    }

    public static function listAll($soAtivos = 1)
    {
        return self::with([])
            ->when($soAtivos, function (Builder $q) {
                $q->where('FIM_LOTACAO_ATIVA', 1);
            })->get();
    }

    public static function pesquisar($requisicao)
    {
        return self::when($requisicao->FIM_LOTACAO_DESCRICAO, function (Builder $query) use ($requisicao) {
            return $query->where("FIM_LOTACAO_DESCRICAO", "like", "%" . $requisicao->FIM_LOTACAO_DESCRICAO . "%");
        })
            ->where('FIM_LOTACAO_ATIVA', '=', 1)
            ->orderBy('FIM_LOTACAO_DESCRICAO')
            ->get();
    }

    public static function buscar($id)
    {
        return self::find($id);
    }
}
