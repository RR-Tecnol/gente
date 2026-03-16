<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int TIPO_DOCUMENTO_ID
 * @property string TIPO_DOCUMENTO_DESCRICAO
 * @property int TIPO_DOCUMENTO_OBRIGATORIO
 * @property int TIPO_DOCUMENTO_ATIVO
 *
 */
class TipoDocumento extends Model
{
    protected $table = "TIPO_DOCUMENTO";
    protected  $primaryKey = "TIPO_DOCUMENTO_ID";
    public $timestamps = false;
    protected $fillable = [
        "TIPO_DOCUMENTO_DESCRICAO",
        "TIPO_DOCUMENTO_OBRIGATORIO",
        "TIPO_DOCUMENTO_ATIVO",
    ];
    protected $casts = [
        "TIPO_DOCUMENTO_OBRIGATORIO" => "integer",
        "TIPO_DOCUMENTO_ATIVO" => "integer",
    ];

    public static function listar($request)
    {
        return self::when($request->TIPO_DOCUMENTO_DESCRICAO, function (Builder $query) use ($request) {
            $query->where('TIPO_DOCUMENTO_DESCRICAO', 'like', "%$request->TIPO_DOCUMENTO_DESCRICAO%");
        })
            ->when($request->orderBy, function (Builder $query) use ($request) {
                $request->sort = $request->sort ?: 'asc';
                $query->orderBy($request->orderBy, $request->sort);
            })
            ->when(!$request->orderBy, function (Builder $query) {
                $query->orderBy('TIPO_DOCUMENTO_DESCRICAO');
            });
    }

    public static function buscar($id)
    {
        return self::find($id);
    }

    public static function documento($tipoDocumentoId)
    {
        return self::where("TIPO_DOCUMENTO_ID", "=", $tipoDocumentoId)
            ->get();
    }

    public static function obrigatorio()
    {
        return self::where('TIPO_DOCUMENTO_OBRIGATORIO', '=', 1)->get();
    }
}
