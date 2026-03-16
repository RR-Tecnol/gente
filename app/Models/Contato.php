<?php

namespace App\Models;

use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int CONTATO_ID
 * @property int CONTATO_TIPO
 * @property int PESSOA_ID
 * @property string CONTATO_CONTEUDO
 *
 * @method static Contato find(mixed $input)
 */
class Contato extends Model
{
    protected $table = "CONTATO";
    protected $primaryKey = "CONTATO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "CONTATO_TIPO",
        "PESSOA_ID",
        "CONTATO_CONTEUDO",
    ];
    protected $casts = [
        "CONTATO_ID" => "integer",
        "CONTATO_TIPO" => "integer",
        "PESSOA_ID" => "integer",
    ];

    public function contatoTipo()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "CONTATO_TIPO")
            ->where("TABELA_ID", "=", RTG::CONTATO_TIPO)
            ->where("COLUNA_ID", "!=", 0);
    }

    public static function relacionamento()
    {
        return [
            "tipoContato"
        ];
    }

    public static function listar($request)
    {
        if ($request->id)
            return self::with(self::relacionamento())
                ->where("PESSOA_ID", "=", $request->id)
                ->get();
        else
            return self::with(self::relacionamento())
                ->all();
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamento())
            ->find($id);
    }
}
