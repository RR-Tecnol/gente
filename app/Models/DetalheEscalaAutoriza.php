<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer DETALHE_ESCALA_ID
 * @property integer USUARIO_ID
 * @property string DETALHE_ESCALA_AUTORIZA_DATA
 * @property string DETALHE_ESCALA_AUTORIZA_JUSTIFICATIVA
 *
 * @method static DetalheEscalaAutoriza find(array|string|null $post)
 */
class DetalheEscalaAutoriza extends Model
{
    protected $table = "DETALHE_ESCALA_AUTORIZA";
    protected $primaryKey = "DETALHE_ESCALA_ID";
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = [
        "DETALHE_ESCALA_ID",
        "USUARIO_ID",
        "DETALHE_ESCALA_AUTORIZA_DATA",
        "DETALHE_ESCALA_AUTORIZA_JUSTIFICATIVA",
    ];
    protected $casts = [
        "DETALHE_ESCALA_ID" => "integer",
        "USUARIO_ID" => "integer",
    ];

    public function detalhe_escala()
    {
        return $this->hasOne(DetalheEscala::class, 'DETALHE_ESCALA_ID', 'DETALHE_ESCALA_ID');
    }

    public function usuario()
    {
        return $this->hasOne(Usuario::class, 'USUARIO_ID', 'USUARIO_ID');
    }

    public static function relacionamento()
    {
        return [
            "detalhe_escala",
            "usuario"
        ];
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamento())
            ->find($id);
    }
}
