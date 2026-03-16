<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer UF_ID
 * @property string UF_CODIGO
 * @property string UF_SIGLA
 */
class Uf extends Model
{
    protected $table = "UF";
    protected $primaryKey = "UF_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "UF_CODIGO",
        "UF_SIGLA"
    ];

    public function cidades()
    {
        return $this->hasMany(Cidade::class, 'UF_ID', 'UF_ID')
            ->orderBy('CIDADE_NOME');
    }

    public static $relacionamento = [
        'cidades'
    ];

    public static function listar($request)
    {
        return self::with(self::$relacionamento)
            ->when($request->UF_CODIGO, function (Builder $query) use ($request) {
                $query->where('UF_CODIGO', 'like', "%$request->UF_CODIGO%");
            })
            ->when($request->UF_SIGLA, function (Builder $query) use ($request) {
                $query->where('UF_SIGLA', 'like', "%$request->UF_SIGLA%");
            })
            ->when($request->orderBy, function (Builder $query) use ($request) {
                $request->sort = $request->sort ?: 'asc';
                $query->orderBy($request->orderBy, $request->sort);
            })
            ->when(!$request->orderBy, function (Builder $query) {
                $query->orderBy('UF_SIGLA');
            });
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }
}
