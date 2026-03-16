<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tributacao extends Model
{
    use HasFactory;
    protected $table = "TRIBUTACAO";
    protected $primaryKey = "TRIBUTACAO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        'EVENTO_ID_PROVENTO',
        'EVENTO_ID_IMPOSTO',
        'TRIBUTACAO_ATIVA',
        'VINCULO_ID',
    ];

    protected $casts = [
        'EVENTO_ID_PROVENTO' => 'integer',
        'EVENTO_ID_IMPOSTO' => 'integer',
        'TRIBUTACAO_ATIVA' => 'integer',
        'VINCULO_ID' => 'integer',
    ];

    public function vinculo()
    {
        return $this->hasOne(Vinculo::class, 'VINCULO_ID', 'VINCULO_ID');
    }

    public function eventoImposto()
    {
        return $this->hasOne(Evento::class, 'EVENTO_ID', 'EVENTO_ID_IMPOSTO');
    }

    public static $relacionamento = [
        'vinculo',
        'eventoImposto',
    ];

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }

    public static function listar()
    {
        return self::with(self::$relacionamento);
    }
}
