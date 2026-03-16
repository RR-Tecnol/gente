<?php

namespace App\Models;

use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * @property int HISTORICO_ESCALA_ID
 * @property int USUARIO_ID
 * @property int ESCALA_ID
 * @property integer HISTORICO_ESCALA_STATUS
 * @property string HISTORICO_ESCALA_DATA
 * @property integer HISTORICO_ESCALA_ULTIMO
 *
 */
class HistoricoEscala extends Model
{
    protected $table = "HISTORICO_ESCALA";
    protected $primaryKey = "HISTORICO_ESCALA_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;

    protected $fillable = [
        "USUARIO_ID",
        "ESCALA_ID",
        "HISTORICO_ESCALA_STATUS",
        "HISTORICO_ESCALA_DATA",
        "HISTORICO_ESCALA_ULTIMO"
    ];

    protected $casts = [
        "USUARIO_ID" => "integer",
        "ESCALA_ID" => "integer",
        "HISTORICO_ESCALA_STATUS" => "integer",
        "HISTORICO_ESCALA_ULTIMO" => "integer"
    ];

    public function usuario()
    {
        return $this->hasOne(Usuario::class, 'USUARIO_ID', 'USUARIO_ID');
    }

    public function escala()
    {
        return $this->hasOne(Escala::class, 'ESCALA_ID', 'ESCALA_ID');
    }

    public function statusEscala()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "HISTORICO_ESCALA_STATUS")
            ->where("TABELA_ID", "=", RTG::TIPO_STATUS_ESCALA)
            ->where("COLUNA_ID", "!=", 0);
    }

    public static function relacionamento()
    {
        return [
            "usuario",
            "escala.setor.unidade",
            "statusEscala"
        ];
    }

    public static function listar()
    {
        return self::with(self::relacionamento())
            ->paginate();
    }

    public static function buscar($request)
    {
        return self::with(self::relacionamento())
            ->find($request->id);
    }

    public static function setHistoricoEscala($escala, $statusEscalaId)
    {
        foreach ($escala->historicos as $historico) {
            $historico->HISTORICO_ESCALA_ULTIMO = 0;
            $historico->save();
        }
        $historicoEscala = new HistoricoEscala([
            'USUARIO_ID' => Auth::check() ? Auth::id() : 1,
            'ESCALA_ID' => $escala->ESCALA_ID,
            'HISTORICO_ESCALA_STATUS' => $statusEscalaId,
            'HISTORICO_ESCALA_DATA' => date('Y-m-d H:i:s'),
            'HISTORICO_ESCALA_ULTIMO' => 1
        ]);

        $historicoEscala->save();
    }
}
