<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoContato extends Model
{
    use HasFactory;

    protected $table = "TIPO_CONTATO";
    protected $primaryKey = "TIPO_CONTATO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "TIPO_CONTATO_DESCRICAO",
        "TIPO_CONTATO_ATIVO",
    ];
    protected $casts = [
        "TIPO_CONTATO_ID" => "integer",
        "TIPO_CONTATO_ATIVO" => "integer",
    ];

    public static function listAll($soAtivos = 1)
    {
        return self::with([])
            ->when($soAtivos, function (Builder $q) {
                $q->where("TIPO_CONTATO_ATIVO", 1);
            })
            ->get();
    }
}
