<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParametroFinanceiro extends Model
{
    use HasFactory;
    protected $table = "PARAMETRO_FINANCEIRO";
    protected $primaryKey = "PARAMETRO_FINANCEIRO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        'PARAMETRO_FINANCEIRO_NOME',
        'PARAMETRO_FINANCEIRO_DT_CADASTRO',
        'USUARIO_ID',
    ];

    protected $casts = [
        'USUARIO_ID' => 'integer'
    ];

    public function usuario()
    {
        return $this->hasOne(Usuario::class, 'USUARIO_ID', 'USUARIO_ID');
    }

    public function historicosParametros()
    {
        return $this->hasMany(HistoricoParametro::class, 'PARAMETRO_FINANCEIRO_ID', 'PARAMETRO_FINANCEIRO_ID')
            ->orderBy('HISTORICO_PARAMETRO_ID', 'DESC');
    }

    public function historicoUltimo()
    {
        return $this->hasOne(HistoricoParametro::class, 'PARAMETRO_FINANCEIRO_ID', 'PARAMETRO_FINANCEIRO_ID')
            ->where(function ($query) {
                $query->where('HISTORICO_PARAMETRO_FIM', '>=', Carbon::now()->format('Ym'));
            })
            ->where('HISTORICO_PARAMETRO_INICIO', '<=', Carbon::now()->format('Ym'));
    }

    public static $relacionamento = [
        'usuario',
        'historicosParametros.tipo',
        'historicoUltimo.tipo'
    ];

    public static function listar($request)
    {
        return self::with(self::$relacionamento)
            ->when($request->PARAMETRO_FINANCEIRO_NOME, function (Builder $query) use ($request) {
                $query->where('PARAMETRO_FINANCEIRO_NOME', 'like', "%$request->PARAMETRO_FINANCEIRO_NOME%");
            })
            ->orderBy('PARAMETRO_FINANCEIRO_NOME');
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }
}
