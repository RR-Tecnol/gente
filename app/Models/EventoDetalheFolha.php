<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventoDetalheFolha extends Model
{
    use HasFactory;
    protected $table = "EVENTO_DETALHE_FOLHA";
    protected $primaryKey = "EVENTO_DETALHE_FOLHA_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        'EVENTO_ID',
        'DETALHE_FOLHA_ID',
        'EVENTO_DETALHE_FOLHA_VALOR',
    ];

    protected $casts = [
        'EVENTO_DETALHE_FOLHA_VALOR' => 'float'
    ];

    public function evento()
    {
        return $this->hasOne(Evento::class, 'EVENTO_ID', 'EVENTO_ID');
    }
}
