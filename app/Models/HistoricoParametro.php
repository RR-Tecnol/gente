<?php

namespace App\Models;

use App\Casts\Periodo;
use App\MyLibs\RTG;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoricoParametro extends Model
{
    use HasFactory;
    protected $table = "HISTORICO_PARAMETRO";
    protected $primaryKey = "HISTORICO_PARAMETRO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        'HISTORICO_PARAMETRO_INICIO',
        'HISTORICO_PARAMETRO_FIM',
        'HISTORICO_PARAMETRO_VALOR',
        'HISTORICO_PARAMETRO_TIPO',
        'PARAMETRO_FINANCEIRO_ID',
    ];

    protected $casts = [
        'HISTORICO_PARAMETRO_TIPO' => 'integer',
        'PARAMETRO_FINANCEIRO_ID' => 'integer',
        'HISTORICO_PARAMETRO_INICIO' => Periodo::class,
        'HISTORICO_PARAMETRO_FIM' => Periodo::class,
    ];

    public function tipo()
    {
        return $this->hasOne(TabelaGenerica::class, 'COLUNA_ID', 'HISTORICO_PARAMETRO_TIPO')
            ->where('TABELA_ID', RTG::TIPO_PARAMETRO_FINANCEIRO);
    }

    public static $relacionamento = [
        'tipo',
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

    public static function salarioMinimo()
    {
        return self::with([])
            ->where('PARAMETRO_FINANCEIRO_ID', 1)
            ->where('HISTORICO_PARAMETRO_FIM', '>=', Carbon::now()->format('Ym'))
            ->where('HISTORICO_PARAMETRO_INICIO', '<=', Carbon::now()->format('Ym'))
            ->orWhereNull('HISTORICO_PARAMETRO_FIM')
            ->first();
    }
}
