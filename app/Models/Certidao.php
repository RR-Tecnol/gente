<?php

namespace App\Models;

use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 *@property integer CERTIDAO_ID
 *@property integer CERTIDAO_TIPO
 *@property string CERTIDAO_MATRICULA
 *@property integer CERTIDAO_NUMERO
 *@property integer CERTIDAO_LIVRO
 *@property integer CERTIDAO_FOLHA
 *@property integer CARTORIO_ID
 *@property integer PESSOA_ID
 * @method static Certidao find(mixed $input)
 */
class Certidao extends Model
{
    use HasFactory;

    protected $table = "CERTIDAO";
    protected $primaryKey = "CERTIDAO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "CERTIDAO_TIPO",
        "CERTIDAO_MATRICULA",
        "CERTIDAO_NUMERO",
        "CERTIDAO_LIVRO",
        "CERTIDAO_FOLHA",
        "CARTORIO_ID",
        "PESSOA_ID",
    ];
    protected $casts = [
        "CERTIDAO_TIPO" => "integer",
        "CERTIDAO_NUMERO" => "integer",
        "CERTIDAO_LIVRO" => "integer",
        "CERTIDAO_FOLHA" => "integer",
        "CARTORIO_ID" => "integer",
        "PESSOA_ID" => "integer",
    ];

    public function certidaoTipo()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "CERTIDAO_TIPO")
            ->where("TABELA_ID", "=", RTG::CERTIDAO_TIPO)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function cartorio()
    {
        return $this->hasOne(Cartorio::class, "CARTORIO_ID", "CARTORIO_ID");
    }
}
