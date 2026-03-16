<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TipoAlerta extends Model
{
    protected $table = "TIPO_ALERTA";
    protected  $primaryKey = "TIPO_ALERTA_ID";
    public $timestamps = false;
    protected $fillable = [
        "TIPO_ALERTA_DESCRICAO",
        "TIPO_ALERTA_VISIVEL",
        "TIPO_ALERTA_ATIVO",
    ];

    protected $casts = [
        "TIPO_ALERTA_VISIVEL" => "integer",
        "TIPO_ALERTA_ATIVO" => "integer"
    ];

    public static $relacionamento = [];

    public static function listar($request)
    {
        return self::with(self::$relacionamento)
            ->when($request->TIPO_ALERTA_DESCRICAO, function (Builder $query) use ($request) {
                $query->where('TIPO_ALERTA_DESCRICAO', 'like', "%$request->TIPO_ALERTA_DESCRICAO%");
            })
            ->when($request->orderBy, function (Builder $query) use ($request) {
                $request->sort = $request->sort ?: 'asc';
                $query->orderBy($request->orderBy, $request->sort);
            })
            ->when(!$request->orderBy, function (Builder $query) {
                $query->orderBy('TIPO_ALERTA_DESCRICAO');
            });
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }
}
