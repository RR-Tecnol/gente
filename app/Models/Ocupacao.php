<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Ocupacao extends Model
{
    protected $table = "OCUPACAO";
    protected $primaryKey = "OCUPACAO_ID";
    public $timestamps = false;
    protected $fillable = [
        "OCUPACAO_NOME",
        "OCUPACAO_CBO",
        "OCUPACAO_ATIVA"
    ];

    protected $casts = [
        "OCUPACAO_CBO" => "integer",
        "OCUPACAO_ATIVA" => "integer",
    ];

    public static $relacionamento = [];

    public static function listar($request)
    {
        return self::with(self::$relacionamento)
            ->when($request->OCUPACAO_NOME, function (Builder $query) use ($request) {
                $query->where('OCUPACAO_NOME', 'like', "%$request->OCUPACAO_NOME%");
            })
            ->when($request->OCUPACAO_CBO, function (Builder $query) use ($request) {
                $query->where('OCUPACAO_CBO', 'like', "%$request->OCUPACAO_CBO%");
            })
            ->when($request->orderBy, function (Builder $query) use ($request) {
                $request->sort = $request->sort ?: 'asc';
                $query->orderBy($request->orderBy, $request->sort);
            })
            ->when(!$request->orderBy, function (Builder $query) {
                $query->orderBy('OCUPACAO_NOME');
            });
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }

    public static function search($valorPesquisa, $apenasAtivos = 1)
    {
        return self::with([])
            ->where("OCUPACAO_NOME", "like", "%$valorPesquisa%")
            ->when($apenasAtivos, function ($q) {
                $q->where("OCUPACAO_ATIVA", 1);
            })->paginate(10);
    }
}
