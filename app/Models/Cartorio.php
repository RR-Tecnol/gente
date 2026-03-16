<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cartorio extends Model
{
    protected $table = "CARTORIO";
    protected $primaryKey = "CARTORIO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        'CARTORIO_NOME',
        'CIDADE_ID',
    ];

    protected $casts = [
        'CARTORIO_ID' => 'integer',
        'CIDADE_ID' => 'integer',
    ];

    public function cidade()
    {
        return $this->hasOne(Cidade::class, 'CIDADE_ID', 'CIDADE_ID');
    }

    public static $relacionamento = [
        'cidade.uf'
    ];

    public static function listar($request)
    {
        return self::with(self::$relacionamento)
            ->when($request->CARTORIO_NOME, function (Builder $query) use ($request) {
                $query->where('CARTORIO_NOME', 'like', "%$request->CARTORIO_NOME%");
            })
            ->when($request->CIDADE_NOME, function (Builder $query) use ($request) {
                $query->whereHas('cidade', function ($query) use ($request) {
                    $query->where('CIDADE_NOME', 'like', "%$request->CIDADE_NOME%");
                });
            })
            ->when($request->UF_ID, function (Builder $query) use ($request) {
                $query->whereHas('cidade.uf', function ($query) use ($request) {
                    $query->where('UF_ID', $request->UF_ID);
                });
            })
            ->when($request->orderBy, function (Builder $query) use ($request) {
                $request->sort = $request->sort ?: 'asc';
                $query->orderBy($request->orderBy, $request->sort);
            })
            ->when(!$request->orderBy, function (Builder $query) {
                $query->orderBy('CARTORIO_NOME');
            });
    }

    public static function search($valorPesquisa)
    {
        return self::with(self::$relacionamento)
            ->when($valorPesquisa, function (Builder $query) use ($valorPesquisa) {
                $query
                    ->where('CARTORIO_NOME', 'like', "%$valorPesquisa%")
                    ->orWhereHas('cidade', function ($query) use ($valorPesquisa) {
                        $query->where('CIDADE_NOME', 'like', "%$valorPesquisa%");
                    })
                    ->orWhereHas('cidade.uf', function ($query) use ($valorPesquisa) {
                        $query->where('UF_SIGLA', 'like', "%$valorPesquisa%")->orWhere('UF_SIGLA', 'like', "%$valorPesquisa%");
                    });
            })
            ->orderBy('CARTORIO_NOME')->paginate();
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }
}
