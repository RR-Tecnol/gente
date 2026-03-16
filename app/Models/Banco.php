<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 *@property integer BANCO_ID
 *@property string BANCO_CODIGO
 *@property string BANCO_NOME
 *@property integer BANCO_OFICIAL
 *@property integer BANCO_ATIVO
 * @method static Banco find($BANCO_ID)
 */
class Banco extends Model
{
    protected $table = "BANCO";
    protected $primaryKey = "BANCO_ID";
    public $timestamps = false;
    protected $fillable = [
        "BANCO_CODIGO",
        "BANCO_NOME",
        "BANCO_OFICIAL",
        "BANCO_ATIVO",
    ];

    protected $casts = [
        "BANCO_OFICIAL" => "integer",
        "BANCO_ATIVO" => "integer"
    ];

    public static function listar($request)
    {
        return self::when($request->BANCO_CODIGO, function (Builder $query) use ($request) {
            $query->where('BANCO_CODIGO', 'like', "%$request->BANCO_CODIGO%");
        })
            ->when($request->BANCO_NOME, function (Builder $query) use ($request) {
                $query->where('BANCO_NOME', 'like', "%$request->BANCO_NOME%");
            })
            ->when(($request->BANCO_OFICIAL != null || $request->BANCO_OFICIAL === 0), function (Builder $query) use ($request) {
                $query->where('BANCO_OFICIAL', '=', $request->BANCO_OFICIAL);
            })
            ->when($request->orderBy, function (Builder $query) use ($request) {
                $request->sort = $request->sort ?: 'asc';
                $query->orderBy($request->orderBy, $request->sort);
            })
            ->when(!$request->orderBy, function (Builder $query) {
                $query->orderBy('BANCO_NOME');
            });
    }

    public static function buscar($id)
    {
        return self::find($id);
    }

    public static function search($valorPesquisa, $apenasAtivos = 1)
    {
        return self::with([])->where('BANCO_CODIGO', $valorPesquisa)
            ->orWhere('BANCO_NOME', 'like', "%$valorPesquisa%")
            ->when($apenasAtivos, function ($q) {
                $q->where('BANCO_ATIVO', 1);
            })->paginate(10);
    }
}
