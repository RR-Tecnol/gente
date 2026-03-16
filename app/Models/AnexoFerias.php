<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int ANEXO_FERIAS_ID
 * @property int FERIAS_ID
 * @property string ANEXO_FERIAS_ARQUIVO
 * @property string ANEXO_FERIAS_DESCRICAO
 *
 */
class AnexoFerias extends Model
{
    protected $table = "ANEXO_FERIAS";
    protected $primaryKey = "ANEXO_FERIAS_ID";
    public $timestamps = false;
    protected $fillable = [
        "FERIAS_ID",
        "ANEXO_FERIAS_ARQUIVO",
        "ANEXO_FERIAS_DESCRICAO",
        "ANEXO_FERIAS_EXTENSAO",
        "ANEXO_FERIAS_NOME"
    ];

    protected $hidden = [
        "ANEXO_FERIAS_ARQUIVO"
    ];

    public function ferias()
    {
        return $this->hasOne(Ferias::class, 'FERIAS_ID', 'FERIAS_ID');
    }

    public static $relacionamento = [
        'ferias'
    ];

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }
}
