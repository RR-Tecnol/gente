<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    APURACAO_ID
 * @property int    FUNCIONARIO_ID
 * @property string APURACAO_COMPETENCIA  ex: "2025-03"
 * @property float  APURACAO_HORAS_TRAB
 * @property float  APURACAO_HORAS_EXTRA
 * @property float  APURACAO_HORAS_FALTA
 * @property string APURACAO_STATUS  ABERTA|FECHADA|AJUSTADA
 */
class ApuracaoPonto extends Model
{
    protected $table = 'APURACAO_PONTO';
    protected $primaryKey = 'APURACAO_ID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    protected $fillable = [
        'FUNCIONARIO_ID',
        'APURACAO_COMPETENCIA',
        'APURACAO_HORAS_TRAB',
        'APURACAO_HORAS_EXTRA',
        'APURACAO_HORAS_FALTA',
        'APURACAO_STATUS',
        'APURACAO_FECHADA_EM',
        'APURACAO_FECHADA_POR',
    ];

    protected $casts = [
        'FUNCIONARIO_ID' => 'integer',
        'APURACAO_HORAS_TRAB' => 'float',
        'APURACAO_HORAS_EXTRA' => 'float',
        'APURACAO_HORAS_FALTA' => 'float',
    ];

    public function funcionario()
    {
        return $this->hasOne(Funcionario::class, 'FUNCIONARIO_ID', 'FUNCIONARIO_ID');
    }

    public function justificativas()
    {
        return $this->hasMany(JustificativaPonto::class, 'APURACAO_ID', 'APURACAO_ID');
    }

    public function fechadaPor()
    {
        return $this->hasOne(Funcionario::class, 'FUNCIONARIO_ID', 'APURACAO_FECHADA_POR');
    }

    public static function relacionamento()
    {
        return [
            'funcionario.pessoa',
            'justificativas',
        ];
    }

    public static function pesquisar($req)
    {
        return self::with(self::relacionamento())
            ->when($req->APURACAO_COMPETENCIA, fn($q) => $q->where('APURACAO_COMPETENCIA', $req->APURACAO_COMPETENCIA))
            ->when($req->FUNCIONARIO_ID, fn($q) => $q->where('FUNCIONARIO_ID', $req->FUNCIONARIO_ID))
            ->when($req->APURACAO_STATUS, fn($q) => $q->where('APURACAO_STATUS', $req->APURACAO_STATUS))
            ->orderBy('APURACAO_COMPETENCIA', 'desc')
            ->paginate(30);
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamento())->find($id);
    }
}
