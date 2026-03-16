<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    JUSTIFICATIVA_ID
 * @property int    APURACAO_ID
 * @property string JUSTIFICATIVA_DATA
 * @property string JUSTIFICATIVA_MOTIVO
 * @property string JUSTIFICATIVA_STATUS  PENDENTE|APROVADA|REJEITADA
 * @property int    GESTOR_ID
 * @property string GESTOR_OBS
 */
class JustificativaPonto extends Model
{
    protected $table = 'JUSTIFICATIVA_PONTO';
    protected $primaryKey = 'JUSTIFICATIVA_ID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    protected $fillable = [
        'APURACAO_ID',
        'JUSTIFICATIVA_DATA',
        'JUSTIFICATIVA_MOTIVO',
        'JUSTIFICATIVA_STATUS',
        'GESTOR_ID',
        'GESTOR_OBS',
        'GESTOR_DECISAO_EM',
    ];

    protected $casts = [
        'APURACAO_ID' => 'integer',
        'GESTOR_ID' => 'integer',
    ];

    public function apuracao()
    {
        return $this->hasOne(ApuracaoPonto::class, 'APURACAO_ID', 'APURACAO_ID');
    }

    public function gestor()
    {
        return $this->hasOne(Funcionario::class, 'FUNCIONARIO_ID', 'GESTOR_ID');
    }

    public static function relacionamento()
    {
        return [
            'apuracao.funcionario.pessoa',
            'gestor.pessoa',
        ];
    }

    /** Retorna justificativas PENDENTES para a fila do gestor. */
    public static function pendentes(int $setorId = null)
    {
        return self::with(self::relacionamento())
            ->where('JUSTIFICATIVA_STATUS', 'PENDENTE')
            ->when($setorId, fn($q) => $q->whereHas(
                'apuracao.funcionario.detalheEscalas.escala',
                fn($sq) => $sq->where('SETOR_ID', $setorId)
            ))
            ->orderBy('JUSTIFICATIVA_DATA')
            ->get();
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamento())->find($id);
    }
}
