<?php

namespace App\Models;

use App\Observers\DocumentoObserver;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int DOCUMENTO_ID
 * @property int TIPO_DOCUMENTO_ID
 * @property int PESSOA_ID
 * @property string DOCUMENTO_NUMERO
 *
 * @method static Documento find(array|string|null $post)
 */
class Documento extends Model
{
    protected $table = "DOCUMENTO";
    protected $primaryKey = "DOCUMENTO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "TIPO_DOCUMENTO_ID",
        "PESSOA_ID",
        "DOCUMENTO_NUMERO",
    ];
    protected $casts = [
        "TIPO_DOCUMENTO_ID" => "integer",
        "PESSOA_ID" => "integer",
        "DOCUMENTO_ID" => "integer",
    ];

    public static $relacionamentos = [
        "tipoDocumento",
    ];

    public function tipoDocumento()
    {
        return $this->hasOne(TipoDocumento::class, 'TIPO_DOCUMENTO_ID', 'TIPO_DOCUMENTO_ID');
    }

    public function pessoa()
    {
        return $this->hasOne(Pessoa::class, "PESSOA_ID", "PESSOA_ID");
    }

    public static function relacionamento()
    {
        return [
            "tipo_documento"
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
