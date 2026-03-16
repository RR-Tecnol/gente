<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer DETALHE_ESCALA_ITEM_ID
 * @property integer DETALHE_ESCALA_ID
 * @property integer TURNO_ID
 * @property string DETALHE_ESCALA_ITEM_DATA
 * @property integer DETALHE_ESCALA_ITEM_FALTA
 * @property integer DETALHE_ESCALA_ITEM_ATRASO
 * @property string DETALHE_ESCALA_ITEM_OBSERVACAO
 */
class DetalheEscalaItem extends Model
{
    protected $table = "DETALHE_ESCALA_ITEM";
    protected $primaryKey = "DETALHE_ESCALA_ITEM_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "DETALHE_ESCALA_ID",
        "TURNO_ID",
        "DETALHE_ESCALA_ITEM_DATA",
        "DETALHE_ESCALA_ITEM_FALTA",
        "DETALHE_ESCALA_ITEM_ATRASO",
        "DETALHE_ESCALA_ITEM_OBSERVACAO"
    ];

    protected $casts = [
        "DETALHE_ESCALA_ID" => 'integer',
        "TURNO_ID" => 'integer',
        "DETALHE_ESCALA_ITEM_FALTA" => 'integer',
        "DETALHE_ESCALA_ITEM_ATRASO" => 'integer'
    ];

    public function detalheEscala()
    {
        return $this->hasOne(DetalheEscala::class, 'DETALHE_ESCALA_ID', 'DETALHE_ESCALA_ID');
    }

    public function turno()
    {
        return $this->hasOne(Turno::class, 'TURNO_ID', 'TURNO_ID');
    }

    public function abonoFalta()
    {
        return $this->hasOne(AbonoFalta::class, 'DETALHE_ESCALA_ITEM_ID', 'DETALHE_ESCALA_ITEM_ID');
    }

    public static function relacionamento()
    {
        return [
            'detalheEscala.escala.historicoUltimo.statusEscala',
            'detalheEscala.funcionario.pessoa',
            'detalheEscala.escala.setor.unidade',
            'detalheEscala.atribuicao',
            'turno',
            'abonoFalta',
        ];
    }

    public static function listar()
    {
        return self::with(self::relacionamento())
            ->all();
    }

    public static function pesquisar($requisicao)
    {
        //        dd($requisicao->input());
        return self::with(self::relacionamento())
            ->when($requisicao->TURNO_ID, function (Builder $query) use ($requisicao) {
                return $query->where("TURNO_ID", "=", $requisicao->TURNO_ID);
            })
            ->when($requisicao->DETALHE_ESCALA_ITEM_DATA, function (Builder $query) use ($requisicao) {
                return $query->where("DETALHE_ESCALA_ITEM_DATA", "=", $requisicao->DETALHE_ESCALA_ITEM_DATA);
            })
            ->when($requisicao->FUNCIONARIO_NOME, function (Builder $query) use ($requisicao) {
                return $query->whereHas("detalheEscala.funcionario.pessoa", function ($query) use ($requisicao) {
                    return $query->where("PESSOA_NOME", "like", "%$requisicao->FUNCIONARIO_NOME%");
                });
            })
            ->when($requisicao->SETOR_ID, function (Builder $query) use ($requisicao) {
                return $query->whereHas("detalheEscala.escala.setor", function ($query) use ($requisicao) {
                    return $query->where("SETOR_ID", "=", $requisicao->SETOR_ID);
                });
            })
            ->when($requisicao->UNIDADE_ID, function (Builder $query) use ($requisicao) {
                return $query->whereHas("detalheEscala.escala.setor.unidade", function ($query) use ($requisicao) {
                    return $query->where("UNIDADE_ID", "=", $requisicao->UNIDADE_ID);
                });
            })
            ->when($requisicao->DETALHE_ESCALA_ITEM_FALTA, function (Builder $query) use ($requisicao) {
                return $query->where("DETALHE_ESCALA_ITEM_FALTA", $requisicao->DETALHE_ESCALA_ITEM_FALTA);
            })
            ->when($requisicao->COLUNA_ID, function (Builder $query) use ($requisicao) {
                $query->whereBetween("DETALHE_ESCALA_ITEM_DATA", [date('Y-m-d', strtotime(date('Y-m-1') . '- 1 month')), date('Y-m-t')]);
            })
            ->when($requisicao->UNIDADE_NOME, function (Builder $query) use ($requisicao) {
                return $query->whereHas("detalheEscala.escala.setor.unidade", function ($query) use ($requisicao) {
                    return $query->where("UNIDADE_NOME", "like", "%$requisicao->UNIDADE_NOME%");
                });
            })
            ->when($requisicao->SETOR_NOME, function (Builder $query) use ($requisicao) {
                $query->whereHas("detalheEscala.escala.setor", function ($query) use ($requisicao) {
                    $query->where("SETOR_NOME", "like", "%$requisicao->SETOR_NOME%");
                });
            })
            ->whereHas("detalheEscala.escala.historicoUltimo", function ($query) {
                $query->where("HISTORICO_ESCALA_STATUS", 4);
            })
            ->paginate();
    }

    public static function buscar($request)
    {
        return self::with(self::relacionamento())
            ->find($request);
    }
}
