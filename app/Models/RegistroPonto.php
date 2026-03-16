<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    REGISTRO_PONTO_ID
 * @property int    FUNCIONARIO_ID
 * @property int    TERMINAL_ID
 * @property string REGISTRO_DATA_HORA
 * @property string REGISTRO_TIPO     ENTRADA|PAUSA|RETORNO|SAIDA
 * @property string REGISTRO_ORIGEM   REP_P|REP_A_SENHA|MANUAL
 * @property string REGISTRO_NSR
 * @property string REGISTRO_OBSERVACAO
 */
class RegistroPonto extends Model
{
    protected $table = 'REGISTRO_PONTO';
    protected $primaryKey = 'REGISTRO_PONTO_ID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    protected $fillable = [
        'FUNCIONARIO_ID',
        'TERMINAL_ID',
        'REGISTRO_DATA_HORA',
        'REGISTRO_TIPO',
        'REGISTRO_ORIGEM',
        'REGISTRO_NSR',
        'REGISTRO_OBSERVACAO',
    ];

    protected $casts = [
        'FUNCIONARIO_ID' => 'integer',
        'TERMINAL_ID' => 'integer',
    ];

    public function funcionario()
    {
        return $this->hasOne(Funcionario::class, 'FUNCIONARIO_ID', 'FUNCIONARIO_ID');
    }

    public function terminal()
    {
        return $this->hasOne(TerminalPonto::class, 'TERMINAL_ID', 'TERMINAL_ID');
    }

    public static function relacionamento()
    {
        return [
            'funcionario.pessoa',
            'funcionario.detalheEscalas.escala.setor',
            'terminal',
        ];
    }

    public static function pesquisar($req)
    {
        return self::with(self::relacionamento())
            ->when($req->FUNCIONARIO_ID, fn($q) => $q->where('FUNCIONARIO_ID', $req->FUNCIONARIO_ID))
            ->when($req->DATA_INICIO, fn($q) => $q->whereDate('REGISTRO_DATA_HORA', '>=', $req->DATA_INICIO))
            ->when($req->DATA_FIM, fn($q) => $q->whereDate('REGISTRO_DATA_HORA', '<=', $req->DATA_FIM))
            ->when($req->REGISTRO_TIPO, fn($q) => $q->where('REGISTRO_TIPO', $req->REGISTRO_TIPO))
            ->when($req->REGISTRO_ORIGEM, fn($q) => $q->where('REGISTRO_ORIGEM', $req->REGISTRO_ORIGEM))
            ->when($req->SETOR_ID, fn($q) => $q->whereHas(
                'funcionario.detalheEscalas.escala',
                fn($sq) =>
                $sq->where('SETOR_ID', $req->SETOR_ID)
            ))
            ->orderByDesc('REGISTRO_DATA_HORA')
            ->paginate(50);
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamento())->find($id);
    }
}
