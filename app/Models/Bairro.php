<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property integer $BAIRRO_ID
 * @property string $BAIRRO_NOME
 * @property integer $CIDADE_ID
 * @property Cidade $cidade
 */
class Bairro extends Model
{
    use HasFactory, SoftDeletes;

    const DELETED_AT = 'BAIRRO_DATA_EXCLUSAO';

    protected $table = "BAIRRO";
    protected $primaryKey = "BAIRRO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "BAIRRO_NOME",
        "CIDADE_ID",
    ];
    protected $casts = [
        "CIDADE_ID" => "integer"
    ];

    public function cidade()
    {
        return $this->hasOne(Cidade::class, 'CIDADE_ID', 'CIDADE_ID');
    }
    public static $relacionamentos = [
        'cidade.uf',
    ];

    public static function listar($request)
    {
        return self::with(self::$relacionamentos)
            ->when($request->BAIRRO_NOME, function (Builder $query) use ($request) {
                $query->where('BAIRRO_NOME', 'like', "%$request->BAIRRO_NOME%");
            })
            ->when($request->CIDADE_ID, function (Builder $query) use ($request) {
                $query->where('CIDADE_ID', $request->CIDADE_ID);
            })
            ->when($request->orderBy, function (Builder $query) use ($request) {
                $request->sort = $request->sort ?: 'asc';
                $query->orderBy($request->orderBy, $request->sort);
            })
            ->when(!$request->orderBy, function (Builder $query) {
                $query->orderBy('BAIRRO_NOME');
            });
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamentos)
            ->find($id);
    }

    public static function pesquisar($valorPesquisa)
    {
        return self::with(self::$relacionamentos)
            ->where("BAIRRO_NOME", "like", "%$valorPesquisa%")
            ->paginate();
    }
}
