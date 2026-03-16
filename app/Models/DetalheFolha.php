<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalheFolha extends Model
{
    use HasFactory;
    protected $table = "DETALHE_FOLHA";
    protected $primaryKey = "DETALHE_FOLHA_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        'FOLHA_ID',
        'FUNCIONARIO_ID',
        'PENSIONISTA_ID',
        'DETALHE_FOLHA_PROVENTOS',
        'DETALHE_FOLHA_DESCONTOS',
        'DETALHE_FOLHA_ERRO',
    ];

    protected $casts = [
        'DETALHE_FOLHA_PROVENTOS' => 'float',
        'DETALHE_FOLHA_DESCONTOS' => 'float'
    ];

    public function folha()
    {
        return $this->hasOne(Folha::class, 'FOLHA_ID', 'FOLHA_ID');
    }

    public function EventosDetalhesFolhas()
    {
        return $this->hasMany(EventoDetalheFolha::class, 'DETALHE_FOLHA_ID', 'DETALHE_FOLHA_ID');
    }

    public function funcionario()
    {
        return $this->hasOne(Funcionario::class, 'FUNCIONARIO_ID', 'FUNCIONARIO_ID');
    }
}
