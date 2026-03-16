<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Vinculo extends Model
{
    protected $table = "VINCULO";
    protected $primaryKey = "VINCULO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "VINCULO_NOME",
        "VINCULO_DESCRICAO", // alias — mantido por compatibilidade com front
        "VINCULO_SIGLA",
        "VINCULO_ATIVO",
    ];

    // Permite VINCULO_DESCRICAO como alias de VINCULO_NOME
    public function setVinculoDescricaoAttribute($value)
    {
        $this->attributes['VINCULO_NOME'] = $value;
    }

    public function getVinculoDescricaoAttribute()
    {
        return $this->attributes['VINCULO_NOME'] ?? null;
    }

    protected $casts = [
        "VINCULO_ATIVO" => "integer"
    ];

    public static $relacionamento = [];

    public static function listar($request)
    {
        return self::with(self::$relacionamento)
            ->when($request->VINCULO_DESCRICAO, function (Builder $query) use ($request) {
                $query->where('VINCULO_DESCRICAO', 'like', "%$request->VINCULO_DESCRICAO%");
            })
            ->when($request->VINCULO_SIGLA, function (Builder $query) use ($request) {
                $query->where('VINCULO_SIGLA', 'like', "%$request->VINCULO_SIGLA%");
            })
            ->when($request->orderBy, function (Builder $query) use ($request) {
                $request->sort = $request->sort ?: 'asc';
                $query->orderBy($request->orderBy, $request->sort);
            })
            ->when(!$request->orderBy, function (Builder $query) {
                $query->orderBy('VINCULO_DESCRICAO');
            });
    }

    public static function listAll($soAtivos = 1)
    {
        return self::with([])
            ->when($soAtivos, function (Builder $q) {
                $q->where("VINCULO_ATIVO", 1);
            })
            ->get();
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }
}
