<?php

namespace App\Models;

use App\Casts\Cpf;
use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer DEPENDENTE_ID
 * @property integer DEPENDENTE_TIPO
 * @property string DEPENDENTE_DT_INICIO
 * @property string DEPENDENTE_DT_FIM
 * @property integer PESSOA_ID
 * @property string DEPENDENTE_TIPO_FIM
 * @method static Dependente find(array|string|null $post)
 */
class Dependente extends Model
{
    protected $table = "DEPENDENTE";
    protected $primaryKey = "DEPENDENTE_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "DEPENDENTE_NOME",
        "DEPENDENTE_SEXO",
        "DEPENDENTE_CPF",
        "DEPENDENTE_NASCIMENTO",
        "DEPENDENTE_TIPO",
        "DEPENDENTE_DT_INICIO",
        "DEPENDENTE_DT_FIM",
        "PESSOA_ID",
        "DEPENDENTE_TIPO_FIM",
    ];

    protected $casts = [
        "DEPENDENTE_SEXO" => "integer",
        "DEPENDENTE_CPF" => Cpf::class,
        "DEPENDENTE_TIPO" => "integer",
        "DEPENDENTE_DT_INICIO" => "date:Y-m-d",
        "DEPENDENTE_DT_FIM" => "date:Y-m-d",
        "PESSOA_ID" => "integer",
        "DEPENDENTE_TIPO_FIM" => "integer",
    ];

    public function sexo()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "DEPENDENTE_SEXO")
            ->where("TABELA_ID", "=", RTG::SEXO)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function dependenteTipo()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "DEPENDENTE_TIPO")
            ->where("TABELA_ID", "=", RTG::TIPO_DEPENDENTE)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function dependenteTipoFim()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "DEPENDENTE_TIPO_FIM")
            ->where("TABELA_ID", "=", RTG::TIPO_FINALIZACAO_DEPENDENTE)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function pessoa()
    {
        return $this->hasOne(Pessoa::class, 'PESSOA_ID', 'PESSOA_ID');
    }

    public static function listar($request)
    {
        if ($request->pessoaId)
            return self::where('PESSOA_ID', '=', $request->pessoaId)->get();
        else
            return self::get();
    }

    public static function buscar($id)
    {
        return self::find($id);
    }
}
