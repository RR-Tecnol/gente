<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int CARGO_ID
 * @property string CARGO_NOME
 * @property string CARGO_SIGLA
 * @property string CARGO_ESCOLARIDADE
 * @property string CARGO_REMUNERACAO
 * @property int CARGO_GESTAO
 * @property int CARGO_ATIVO
 *
 */
class Cargo extends Model
{
    protected $table = "CARGO";
    protected $primaryKey = "CARGO_ID";
    public $timestamps = false;
    protected $fillable = [
        "CARGO_NOME",
        "CARGO_SIGLA",
        "CARGO_ESCOLARIDADE",
        "CARGO_GESTAO",
        "CARGO_ATIVO",
    ];

    public function escolaridade()
    {
        return $this->hasOne(TabelaGenerica::class, "TABELA_GENERICA_ID", "CARGO_ESCOLARIDADE");
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
            ->where('CARGO_ATIVO', '=', 1)
            ->orderBy('CARGO_NOME')
            ->paginate();;
    }

    public static function pesquisar($requisicao)
    {
        return self::with(self::relacionamento())
            ->when($requisicao->CARGO_NOME, function (Builder $query) use ($requisicao) {
                return $query->where("CARGO_NOME", "like", "%" . $requisicao->CARGO_NOME . "%");
            })
            ->when($requisicao->CARGO_SIGLA, function (Builder $query) use ($requisicao) {
                return $query->where("CARGO_SIGLA", "like", "%" . $requisicao->CARGO_SIGLA . "%");
            })
            ->when($requisicao->CARGO_ESCOLARIDADE, function (Builder $query) use ($requisicao) {
                return $query->where("CARGO_ESCOLARIDADE", "=", $requisicao->CARGO_ESCOLARIDADE);
            })
            ->when($requisicao->CARGO_GESTAO, function (Builder $query) use ($requisicao) {
                return $query->where("CARGO_GESTAO", "=", $requisicao->CARGO_GESTAO);
            })
            ->where('CARGO_ATIVO', '=', 1)
            ->orderBy('CARGO_NOME')
            ->get();
    }

    public static function buscar($id)
    {
        return self::find($id);
    }
}
