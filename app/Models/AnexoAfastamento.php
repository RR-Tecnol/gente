<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int ANEXO_AFASTAMENTO_ID
 * @property int AFASTAMENTO_ID
 * @property string ANEXO_AFASTAMENTO_ARQUIVO
 * @property string ANEXO_AFASTAMENTO_DESCRICAO
 *
 */
class AnexoAfastamento extends Model
{
    protected $table = "ANEXO_AFASTAMENTO";
    protected $primaryKey = "ANEXO_AFASTAMENTO_ID";
    public $timestamps = false;
    protected $fillable = [
        "AFASTAMENTO_ID",
        "ANEXO_AFASTAMENTO_ARQUIVO",
        "ANEXO_AFASTAMENTO_DESCRICAO",
        "ANEXO_AFASTAMENTO_NOME",
        "ANEXO_AFASTAMENTO_EXTENSAO",
    ];

    protected $hidden = [
        "ANEXO_AFASTAMENTO_ARQUIVO"
    ];

    public function afastamento()
    {
        return $this->hasOne(Afastamento::class, 'AFASTAMENTO_ID', 'AFASTAMENTO_ID');
    }

    public static function relacionamento()
    {
        return [
            "afastamento"
        ];
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamento())
            ->find($id);
    }
}
