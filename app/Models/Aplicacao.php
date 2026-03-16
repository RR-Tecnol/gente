<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer APLICACAO_ID
 * @property string APLICACAO_NOME
 * @property string APLICACAO_ICONE
 * @property string APLICACAO_URL
 * @property integer APLICACAO_GESTAO
 * @property integer APLICACAO_ATIVA
 * @property integer APLICACAO_ORDEM
 * @property integer APLICACAO_PAI_ID
 */
class Aplicacao extends Model
{
    use HasFactory;

    protected $table = "APLICACAO";
    protected $primaryKey = "APLICACAO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "APLICACAO_NOME",
        "APLICACAO_ICONE",
        "APLICACAO_URL",
        "APLICACAO_GESTAO",
        "APLICACAO_ATIVA",
        "APLICACAO_ORDEM",
        "APLICACAO_PAI_ID",
    ];
    protected $casts = [
        "APLICACAO_ID" => "integer",
        "APLICACAO_GESTAO" => "integer",
        "APLICACAO_ATIVA" => "integer",
        "APLICACAO_ORDEM" => "integer",
        "APLICACAO_PAI_ID" => "integer",
    ];
    public static $rels1 = [
        'children.children'
    ];

    public function acessos()
    {
        return $this->hasMany(Acesso::class, "APLICACAO_ID", "APLICACAO_ID");
    }

    public function children()
    {
        return $this->hasMany(Aplicacao::class, 'APLICACAO_PAI_ID', 'APLICACAO_ID')
            ->orderBy('APLICACAO_ORDEM')
            ->orderBy('APLICACAO_NOME');
    }

    public static function listAll()
    {
        return self::with(self::$rels1)
            ->whereNull('APLICACAO_PAI_ID')
            ->orderBy('APLICACAO_ORDEM')
            ->orderBy('APLICACAO_NOME')
            ->get();
    }
}
