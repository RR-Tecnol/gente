<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cidade extends Model
{
    use HasFactory;

    protected $table = "CIDADE";
    protected $primaryKey = "CIDADE_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "CIDADE_IBGE",
        "CIDADE_NOME",
        "UF_ID",
        "CIDADE_UF",
    ];
    protected $casts = [
        "UF_ID" => "integer"
    ];
    protected static $relacionamentos = [
        'uf'
    ];

    public function uf()
    {
        return $this->hasOne(Uf::class, "UF_ID", "UF_ID");
    }

    public static function listar($request)
    {
        return self::with(self::$relacionamentos)
            ->when($request->CIDADE_IBGE, function (Builder $query) use ($request) {
                $query->where('CIDADE_IBGE', 'like', "%$request->CIDADE_IBGE%");
            })
            ->when($request->CIDADE_NOME, function (Builder $query) use ($request) {
                $query->where('CIDADE_NOME', 'like', "%$request->CIDADE_NOME%");
            })
            ->when($request->UF_ID, function (Builder $query) use ($request) {
                $query->where('UF_ID', $request->UF_ID);
            })
            ->when($request->orderBy, function (Builder $query) use ($request) {
                $request->sort = $request->sort ?: 'asc';
                $query->orderBy($request->orderBy, $request->sort);
            })
            ->when(!$request->orderBy, function (Builder $query) {
                $query->orderBy('CIDADE_NOME');
            });
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamentos)
            ->find($id);
    }

    public static function pesquisar($valorPesquisa, $ufId = null)
    {
        return self::with(self::$relacionamentos)
            ->where("CIDADE_IBGE", "like", "%$valorPesquisa%")
            ->orWhere("CIDADE_NOME", "like", "%$valorPesquisa%")
            ->when($ufId, function ($q) use ($ufId) {
                $q->where("UF_ID", $ufId);
            })
            ->paginate(10);
    }
}
