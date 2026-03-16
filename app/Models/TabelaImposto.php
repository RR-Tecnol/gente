<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TabelaImposto extends Model
{
    use HasFactory;
    protected $table = "TABELA_IMPOSTO";
    protected $primaryKey = "TABELA_IMPOSTO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "TABELA_IMPOSTO_LIM_INFERIOR",
        "TABELA_IMPOSTO_LIM_SUPERIOR",
        "TABELA_IMPOSTO_PERCENTUAL",
        "TABELA_IMPOSTO_DEDUCAO",
        "VIGENCIA_IMPOSTO_ID"
    ];

    protected $casts = [];

    public static $relacionamento = [];

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }

    public static function listar()
    {
        return self::with(self::$relacionamento);
    }
}
