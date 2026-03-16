<?php

namespace App\Models;

use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Conselho extends Model
{
    protected $table = "CONSELHO";
    protected $primaryKey = "CONSELHO_ID";
    public $timestamps = false;
    protected $fillable = [
        "CONSELHO_TIPO",
        "CONSELHO_SIGLA",
        "CONSELHO_NOME",
        "CONSELHO_ATIVO",
    ];
    protected static $relacionamentos = [
        'tipo'
    ];

    protected $casts = [
        "CONSELHO_TIPO" => "integer",
        "CONSELHO_ATIVO" => "integer"
    ];

    public function tipo()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "CONSELHO_TIPO")
            ->where("TABELA_ID", "=", RTG::TIPO_CONSELHO_CLASSE)
            ->where("COLUNA_ID", "!=", 0);
    }

    public static function listAll()
    {
        return self::with(self::$relacionamentos)->get();
    }

    public static function listar($request)
    {
        return self::with(self::$relacionamentos)
            ->when($request->CONSELHO_TIPO, function (Builder $query) use ($request) {
                $query->where('CONSELHO_TIPO', $request->CONSELHO_TIPO);
            })
            ->when($request->CONSELHO_SIGLA, function (Builder $query) use ($request) {
                $query->where('CONSELHO_SIGLA', 'like', "%$request->CONSELHO_SIGLA%");
            })
            ->when($request->CONSELHO_NOME, function (Builder $query) use ($request) {
                $query->where('CONSELHO_NOME', 'like', "%$request->CONSELHO_NOME%");
            })
            ->when($request->orderBy, function (Builder $query) use ($request) {
                $request->sort = $request->sort ?: 'asc';
                $query->orderBy($request->orderBy, $request->sort);
            })
            ->when(!$request->orderBy, function (Builder $query) {
                $query->orderBy('CONSELHO_NOME');
            });
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamentos)
            ->find($id);
    }

    public static function search($valorPesquisa, $apenasAtivos = 1)
    {
        return self::with(self::$relacionamentos)
            ->where("CONSELHO_SIGLA", $valorPesquisa)
            ->orWhere("CONSELHO_NOME", "like", "%$valorPesquisa%")
            ->when($apenasAtivos, function ($q) {
                $q->where("CONSELHO_ATIVO", 1);
            })->paginate(10);
    }
}
