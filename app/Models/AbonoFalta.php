<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AbonoFalta extends Model
{
    protected $table = "ABONO_FALTA";
    protected $primaryKey = "DETALHE_ESCALA_ITEM_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "DETALHE_ESCALA_ITEM_ID",
        "USUARIO_ID",
        "ABONO_FALTA_DATA",
        "ABONO_FALTA_JUSTIFICATIVA",
    ];

    protected $casts = [
        "USUARIO_ID" => 'integer',
        "DETALHE_ESCALA_ITEM_ID" => 'integer',
    ];

    public function detalheEscalaItem()
    {
        return $this->hasOne(DetalheEscalaItem::class, 'DETALHE_ESCALA_ITEM_ID', 'DETALHE_ESCALA_ITEM_ID');
    }

    public function usuario()
    {
        return $this->hasOne(Usuario::class, 'USUARIO_ID', 'USUARIO_ID');
    }

    public function anexoAbonoFalta()
    {
        return $this->hasMany(AnexoAbonoFalta::class, 'DETALHE_ESCALA_ITEM_ID', 'DETALHE_ESCALA_ITEM_ID');
    }

    public static $relacionamento = [
        "detalheEscalaItem.detalheEscala.funcionario.pessoa",
        "detalheEscalaItem.detalheEscala.escala.setor.unidade",
        "usuario",
        "anexoAbonoFalta"
    ];

    public static function listar($request)
    {
        return self::with(self::$relacionamento)
            ->when($request->PESSOA_NOME, function (Builder $query) use ($request) {
                $query->whereHas('detalheEscalaItem.detalheEscala.funcionario.pessoa', function (Builder $query) use ($request) {
                    $query->where('PESSOA_NOME', 'like', "%$request->PESSOA_NOME%");
                });
            })
            ->when($request->ABONO_FALTA_DATA, function (Builder $query) use ($request) {
                $query->where('ABONO_FALTA_DATA', '=', $request->ABONO_FALTA_DATA);
            })
            ->when($request->orderBy, function (Builder $query) use ($request) {
                $request->sort = $request->sort ?: 'asc';
                $query->orderBy($request->orderBy, $request->sort);
            })
            ->when(!$request->orderBy, function (Builder $query) {
                $query->orderBy('ABONO_FALTA_DATA', 'desc');
            });
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }
}
