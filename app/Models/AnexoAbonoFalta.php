<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnexoAbonoFalta extends Model
{
    protected $table = "ANEXO_ABONO_FALTA";
    protected $primaryKey = "ANEXO_ABONO_FALTA_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "DETALHE_ESCALA_ITEM_ID",
        "ANEXO_ABONO_FALTA_ARQUIVO",
        "ANEXO_ABONO_FALTA_NOME",
        "ANEXO_ABONO_FALTA_EXTENSAO",
        "ANEXO_ABONO_FALTA_DESCRICAO",
    ];

    protected $hidden = [
        "ANEXO_ABONO_FALTA_ARQUIVO"
    ];

    public function abonoFalta()
    {
        return $this->hasOne(AbonoFalta::class, 'DETALHE_ESCALA_ITEM_ID', 'DETALHE_ESCALA_ITEM_ID');
    }

    public static $relacionamento = [
        'abonoFalta'
    ];

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }
}
