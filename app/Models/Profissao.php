<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int PROFISSAO_ID
 * @property string PROFISSAO_DESCRICAO
 * @property string PROFISSAO_ESCOLARIDADEv
 * @property int PROFISSAO_ATIVA
 *
 */

class Profissao extends Model
{
    protected $table = "PROFISSAO";
    protected $primaryKey = "PROFISSAO_ID";
    public $timestamps = false;
    protected $fillable = [
        "PROFISSAO_DESCRICAO",
        "PROFISSAO_ESCOLARIDADE",
        "PROFISSAO_ATIVA",
    ];

    public function escolaridade()
    {
        return $this->hasOne(TabelaGenerica::class, "TABELA_GENERICA_ID", "PROFISSAO_ESCOLARIDADE");
    }

    public static function relacionamento()
    {
        return [
            "escolaridade"
        ];
    }

    public static function listar()
    {
        return self::with(self::relacionamento())
            ->where('PROFISSAO_ATIVA', '=', 1)
            ->orderBy('PROFISSAO_DESCRICAO')
            ->paginate();
    }

    public static function pesquisar($requisicao)
    {
        return self::with(self::relacionamento())
            ->when($requisicao->PROFISSAO_DESCRICAO, function (Builder $query) use ($requisicao) {
                return $query->where("PROFISSAO_DESCRICAO", "like", "%" . $requisicao->PROFISSAO_DESCRICAO . "%");
            })
            ->when($requisicao->PROFISSAO_ESCOLARIDADE, function (Builder $query) use ($requisicao) {
                return $query->where("PROFISSAO_ESCOLARIDADE", "=", $requisicao->PROFISSAO_ESCOLARIDADE);
            })
            ->where('PROFISSAO_ATIVA', '=', 1)
            ->orderBy('PROFISSAO_DESCRICAO')
            ->get();
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamento())
            ->find($id);
    }
}
