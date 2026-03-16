<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Funcao extends Model
{
    protected $table = "FUNCAO";
    protected $primaryKey = "FUNCAO_ID";
    public $timestamps = false;
    protected $fillable = [
        "FUNCAO_NOME",
        "FUNCAO_SIGLA",
        "FUNCAO_ATIVA",
    ];

    public static function listar()
    {
        return self::where('FUNCAO_ATIVA', '=', 1)
            ->orderBy('FUNCAO_NOME')
            ->paginate();
    }

    public static function pesquisar($requisicao)
    {
        return self::when($requisicao->FUNCAO_NOME, function (Builder $query) use ($requisicao) {
            return $query->where("FUNCAO_NOME", "like", "%" . $requisicao->FUNCAO_NOME . "%");
        })
            ->when($requisicao->FUNCAO_SIGLA, function (Builder $query) use ($requisicao) {
                return $query->where("FUNCAO_SIGLA", "like", "%" . $requisicao->FUNCAO_SIGLA . "%");
            })
            ->where('FUNCAO_ATIVA', '=', 1)
            ->orderBy('FUNCAO_NOME')
            ->get();
    }

    public static function buscar($id)
    {
        return self::find($id);
    }
}
