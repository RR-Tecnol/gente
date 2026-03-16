<?php

namespace App\Models;

use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer PESSOA_BANCO_ID
 * @property integer PESSOA_ID
 * @property integer BANCO_ID
 * @property string PESSOA_BANCO_AGENCIA
 * @property string PESSOA_BANCO_CONTA
 * @property integer PESSOA_BANCO_TIPO_CONTA
 * @property string PESSOA_BANCO_PIX
 * @property integer PESSOA_BANCO_TIPO_PIX
 * @method static PessoaBanco find($pessoaBancoId)
 */
class PessoaBanco extends Model
{
    use HasFactory;

    protected $table = "PESSOA_BANCO";
    protected $primaryKey = "PESSOA_BANCO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "PESSOA_ID",
        "BANCO_ID",
        "PESSOA_BANCO_AGENCIA",
        "PESSOA_BANCO_CONTA",
        "PESSOA_BANCO_TIPO_CONTA",
        "PESSOA_BANCO_PIX",
        "PESSOA_BANCO_TIPO_PIX",
    ];
    protected $casts = [
        "PESSOA_ID" => "integer",
        "BANCO_ID" => "integer",
        "PESSOA_BANCO_TIPO_CONTA" => "integer",
        "PESSOA_BANCO_TIPO_PIX" => "integer",
    ];

    public function pessoa()
    {
        return $this->hasOne(Pessoa::class, "PESSOA_ID", "PESSOA_ID");
    }

    public function banco()
    {
        return $this->hasOne(Banco::class, "BANCO_ID", "BANCO_ID");
    }

    public function tipoPix()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "PESSOA_BANCO_TIPO_PIX")
            ->where("TABELA_ID", "=", RTG::TIPO_PIX)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function tipoConta()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "PESSOA_BANCO_TIPO_CONTA")
            ->where("TABELA_ID", "=", RTG::TIPO_CONTA_BANCARIA)
            ->where("COLUNA_ID", "!=", 0);
    }
}
