<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int SCRIPT_ID
 * @property string SCRIPT_DESCRICAO
 * @property string SCRIPT_CONSULTA
 */
class Script extends Model
{
    protected $table = "SCRIPT";
    protected $primaryKey = "SCRIPT_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "SCRIPT_DESCRICAO",
        "SCRIPT_CONSULTA",
    ];
    protected $casts = [
        "SCRIPT_ID" => "integer",
        "SCRIPT_DESCRICAO" => "string",
        "SCRIPT_CONSULTA" => "string",
    ];

    public static $relacionamento = [];

    public static function listar($request)
    {
        return self::with(self::$relacionamento)
            ->when(!$request->orderBy, function (Builder $query) {
                $query->orderBy('SCRIPT_ID', 'ASC');
            });
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }
}
