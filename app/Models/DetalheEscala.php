<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int DETALHE_ESCALA_ID
 * @property int ESCALA_ID
 * @property int FUNCIONARIO_ID
 * @property int CARGO_ID
 * @property int TURNO_ID
 * @property int FUNCAO_ID
 * @property string DETALHE_ESCALA_DATA
 * @property string DETALHE_ESCALA_OBSERVACAO
 * @property string DETALHE_ESCALA_ATIVO
 *
 * @method static DetalheEscala find(array|string|null $post)
 */
class DetalheEscala extends Model
{
    protected $table = "DETALHE_ESCALA";
    protected $primaryKey = "DETALHE_ESCALA_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "ESCALA_ID",
        "FUNCIONARIO_ID",
        "ATRIBUICAO_ID",
        "DETALHE_ESCALA_OBSERVACAO",
        "DETALHE_ESCALA_SALDO",
        "DETALHE_ESCALA_VALOR",
        "TIPO_CALCULO_ID",
    ];
    protected $casts = [
        "DETALHE_ESCALA_ID" => "integer",
        "ESCALA_ID" => "integer",
        "FUNCIONARIO_ID" => "integer",
        "ATRIBUICAO_ID" => "integer",
        "DETALHE_ESCALA_SALDO" => "integer",
    ];

    public function escala()
    {
        return $this->hasOne(Escala::class, 'ESCALA_ID', 'ESCALA_ID');
    }

    public function funcionario()
    {
        return $this->hasOne(Funcionario::class, 'FUNCIONARIO_ID', 'FUNCIONARIO_ID');
    }

    public function atribuicao()
    {
        return $this->hasOne(Atribuicao::class, 'ATRIBUICAO_ID', 'ATRIBUICAO_ID');
    }

    public function detalheEscalaAlertas()
    {
        return $this->hasMany(DetalheEscalaAlerta::class, 'DETALHE_ESCALA_ID', 'DETALHE_ESCALA_ID')
            ->where('DETALHE_ESCALA_ALERTA_ULTIMO', 1);
    }

    public function detalheEscalaAutoriza()
    {
        return $this->hasOne(DetalheEscalaAutoriza::class, 'DETALHE_ESCALA_ID', 'DETALHE_ESCALA_ID');
    }

    public function detalheEscalaItens()
    {
        return $this->hasMany(DetalheEscalaItem::class, "DETALHE_ESCALA_ID", "DETALHE_ESCALA_ID")
            ->orderBy('DETALHE_ESCALA_ITEM_DATA', 'asc');
    }

    public static function relacionamento()
    {
        return [
            "escala",
            "funcionario.lotacoes.vinculo",
            "funcionario.pessoa",
            "atribuicao",
            "detalheEscalaAutoriza",
            "detalheEscalaAlertas.tipoAlerta",
            "detalheEscalaItens.turno"
        ];
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamento())
            ->find($id);
    }

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($detalheEscala) {
            $detalheEscala->detalheEscalaItens()->delete();
            $detalheEscala->detalheEscalaAlertas()->delete();
            $detalheEscala->detalheEscalaAutoriza()->delete();
        });
    }
}
