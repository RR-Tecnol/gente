<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int FUNCIONARIO_ID
 * @property int DETALHE_ESCALA_ITEM_ID
 * @property int USUARIO_ID
 * @property string SUBSTITUICAO_ESCALA_DATA
 * @property string SUBSTITUICAO_ESCALA_MOTIVO
 * @property string SUBSTITUICAO_ESCALA_OBSERVACAO
 *
 */

class SubstituicaoEscala extends Model
{
    protected $table = "SUBSTITUICAO_ESCALA";
    protected $primaryKey = "SUBSTITUICAO_ESCALA_ID";
    public static $snakeAttributes = false;
    public $timestamps = false;
    protected $fillable = [
        "FUNCIONARIO_ID",
        "DETALHE_ESCALA_ITEM_ID",
        "SUBSTITUICAO_ESCALA_DATA",
        "SUBSTITUICAO_ESCALA_JUSTIFICATIVA"
    ];

    protected $casts = [
        "FUNCIONARIO_ID" => 'integer',
        "DETALHE_ESCALA_ITEM_ID" => 'integer',
    ];

    public function funcionario()
    {
        return $this->hasOne(Funcionario::class, 'FUNCIONARIO_ID', 'FUNCIONARIO_ID');
    }
    public function detalheEscalaItem()
    {
        return $this->hasOne(DetalheEscalaItem::class, 'DETALHE_ESCALA_ITEM_ID', 'DETALHE_ESCALA_ITEM_ID');
    }

    public static function relacionamento()
    {
        return [
            "funcionario.pessoa.funcionarios",
            "detalheEscalaItem.detalheEscala.escala.setor.unidade",
            "detalheEscalaItem.detalheEscala.funcionario.pessoa.funcionarios",
        ];
    }

    public static function listar($request)
    {
        return self::with(self::relacionamento())
            ->when($request->PESSOA_NOME, function (Builder $query) use ($request) {
                $query->whereHas('funcionario.pessoa.funcionarios.pessoa', function (Builder $query) use ($request) {
                    $query->where('PESSOA_NOME', 'like', "%$request->PESSOA_NOME%");
                });
            })
            ->when($request->SUBSTITUICAO_ESCALA_DATA, function (Builder $query) use ($request) {
                $query->whereHas('detalheEscalaItem', function ($query) use ($request) {
                    $query->where('DETALHE_ESCALA_ITEM_DATA', '=', $request->SUBSTITUICAO_ESCALA_DATA);
                });
            })
            ->orderBy('SUBSTITUICAO_ESCALA_DATA', 'desc');
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamento())
            ->find($id);
    }
}
